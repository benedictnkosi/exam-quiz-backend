<?php

namespace App\Command;

use App\Entity\HeyGenVideo;
use App\Repository\WordReplacementRepository;
use App\Service\HeyGenService;
use App\Service\OpenAIService;
use App\Service\SabcDigitalScraper;
use App\Service\YouTubeTranscriptService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Google\Client as GoogleClient;
use Google\Service\YouTube as GoogleYouTube;
use Google\Service\YouTube\Video as GoogleYouTubeVideo;
use Google\Service\YouTube\VideoSnippet as GoogleYouTubeVideoSnippet;
use Google\Service\YouTube\VideoStatus as GoogleYouTubeVideoStatus;
use Google\Http\MediaFileUpload;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:sabc-past-four-hours-direct',
    description: 'Create video from SABC Digital News videos published in the last 4 hours'
)]
class SabcPastFourHoursDirectCommand extends Command
{
    private HttpClientInterface $httpClient;
    private SabcDigitalScraper $scraper;
    private YouTubeTranscriptService $transcriptService;
    private OpenAIService $openAIService;
    private HeyGenService $heyGenService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private WordReplacementRepository $wordReplacementRepository;

    public function __construct(
        HttpClientInterface $httpClient,
        SabcDigitalScraper $scraper,
        YouTubeTranscriptService $transcriptService,
        OpenAIService $openAIService,
        HeyGenService $heyGenService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        WordReplacementRepository $wordReplacementRepository
    ) {
        $this->httpClient = $httpClient;
        $this->scraper = $scraper;
        $this->transcriptService = $transcriptService;
        $this->openAIService = $openAIService;
        $this->heyGenService = $heyGenService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->wordReplacementRepository = $wordReplacementRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('avatar-id', null, InputOption::VALUE_OPTIONAL, 'HeyGen Avatar ID', '0d457d33c46049f0b42b538abfc8913b')
            ->addOption('voice-id', null, InputOption::VALUE_OPTIONAL, 'HeyGen Voice ID', 'QOdz6iaNL4YniX0zO8BV')
            ->addOption('no-burn', null, InputOption::VALUE_NONE, 'Do not burn captions into the video (copy original video)')
            ->addOption('upload-youtube', null, InputOption::VALUE_NONE, 'Upload the created video directly to YouTube (YouTube Data API)')
            ->addOption('privacy', null, InputOption::VALUE_OPTIONAL, 'YouTube privacyStatus (public|unlisted|private)', 'public')
            ->addOption('max-videos', null, InputOption::VALUE_OPTIONAL, 'Maximum number of recent SABC videos to process (testing helper)', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $avatarId = $input->getOption('avatar-id');
        $voiceId = $input->getOption('voice-id');
        $maxVideosOpt = (string)$input->getOption('max-videos');
        $maxVideos = (int)$maxVideosOpt;
        if ($maxVideos <= 0) {
            $maxVideos = 20;
        }

        $output->writeln('<info>Searching for SABC Digital News videos from the last 4 hours...</info>');

        // Find all videos from the last 4 hours
        $videos = $this->findVideosFromLastFourHours($maxVideos);

        if (empty($videos)) {
            $output->writeln('<error>No videos found from the last 4 hours</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Found ' . count($videos) . ' video(s):</info>');
        foreach ($videos as $video) {
            $output->writeln("  Video ID: {$video['id']} - {$video['title']}");
        }

        // Fetch transcripts for all videos and merge them
        $output->writeln('<info>Fetching transcripts for all videos...</info>');
        $mergedTranscripts = [];
        
        foreach ($videos as $video) {
            $videoUrl = "https://www.youtube.com/watch?v={$video['id']}";
            $output->writeln("  Fetching transcript for: {$video['title']}");
            
            $transcript = $this->transcriptService->fetchTranscript($videoUrl, 'text', false, false);

            if (!is_string($transcript) || trim($transcript) === '') {
                $json = $this->transcriptService->fetchTranscript($videoUrl, 'json', false, false);
                if (is_array($json) && isset($json['segments']) && is_array($json['segments'])) {
                    $transcript = trim(implode("\n", array_map(static function ($seg) { 
                        return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : ''; 
                    }, $json['segments'])));
                }
            }

            if ($transcript && trim($transcript) !== '') {
                $mergedTranscripts[] = [
                    'title' => $video['title'],
                    'transcript' => $transcript
                ];
                $output->writeln("  ✓ Transcript fetched (" . strlen($transcript) . " characters)");
            } else {
                $output->writeln("  ✗ Failed to fetch transcript for: {$video['title']}");
            }
        }

        if (empty($mergedTranscripts)) {
            $output->writeln('<error>Failed to fetch any transcripts</error>');
            return Command::FAILURE;
        }

        // Merge all transcripts into one file
        $output->writeln('<info>Merging transcripts...</info>');
        $mergedContent = '';
        foreach ($mergedTranscripts as $item) {
            $mergedContent .= "=== " . $item['title'] . " ===\n\n";
            $mergedContent .= $item['transcript'] . "\n\n";
        }
        
        $output->writeln('<info>Merged transcript length: ' . strlen($mergedContent) . ' characters</info>');

        // Save merged transcript to file and upload to OpenAI
        $output->writeln('<info>Saving merged transcript to file...</info>');
        $transcriptDir = sys_get_temp_dir() . '/heygen_transcripts';
        if (!is_dir($transcriptDir)) {
            if (!@mkdir($transcriptDir, 0755, true) && !is_dir($transcriptDir)) {
                $output->writeln('<error>Failed to create transcript directory: ' . $transcriptDir . '</error>');
                return Command::FAILURE;
            }
        }
        
        if (!is_writable($transcriptDir)) {
            $output->writeln('<error>Transcript directory is not writable: ' . $transcriptDir . '</error>');
            return Command::FAILURE;
        }
        
        $transcriptFile = $transcriptDir . '/sabc_merged_' . date('Y-m-d_H-i-s') . '.txt';
        $result = @file_put_contents($transcriptFile, $mergedContent);
        if ($result === false) {
            $output->writeln('<error>Failed to write transcript file: ' . $transcriptFile . '</error>');
            return Command::FAILURE;
        }
        
        // Create UploadedFile object for OpenAI service
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $transcriptFile,
            basename($transcriptFile),
            'text/plain',
            null,
            true
        );
        
        $output->writeln('<info>Uploading merged transcript to OpenAI...</info>');
        $uploadResponse = $this->openAIService->uploadFile($uploadedFile);
        
        if (!isset($uploadResponse['id'])) {
            $output->writeln('<error>Failed to upload transcript to OpenAI</error>');
            unlink($transcriptFile);
            return Command::FAILURE;
        }
        
        $fileId = $uploadResponse['id'];
        $output->writeln("OpenAI File ID: {$fileId}");

        // Read the transcript file content and use it directly
        $transcriptContent = file_get_contents($transcriptFile);
        
        // Generate 45-second script with Dan as anchor using merged transcript
        // Target 57 seconds to compensate for faster TTS reading speed (actual result will be ~45 seconds)
        $output->writeln('<info>Generating 45-second script from merged transcripts...</info>');
        
        // Determine greeting based on time of day (runs at 12pm, 4pm, 8pm)
        $currentHour = (int)date('G'); // 24-hour format (0-23)
        $greeting = 'Hi there, here is your midday update.'; // Default for 12pm and 4pm
        if ($currentHour >= 18) { // 6pm or later (covers 8pm)
            $greeting = 'Hi there, here is your evening update.';
        } elseif ($currentHour < 12) { // Before noon (shouldn't happen, but just in case)
            $greeting = 'Hi there, here is your morning update.';
        }elseif ($currentHour > 13 && $currentHour < 18) { // Before noon (shouldn't happen, but just in case)
            $greeting = 'Hi there, here is your afternoon update.';
        }
        
        $script = $this->openAIService->generateVideoScriptFromTranscript($transcriptContent, 5, 50, 'Dan', $greeting);

        if (!$script) {
            $output->writeln('<error>Failed to generate script</error>');
            $this->openAIService->deleteFile($fileId);
            unlink($transcriptFile);
            return Command::FAILURE;
        }

        $output->writeln('<info>Script generated successfully</info>');
        $this->logger->info('SABC Past 4 Hours script', [
            'videoCount' => count($videos),
            'videos' => array_map(fn($v) => ['id' => $v['id'], 'title' => $v['title']], $videos),
            'script' => $script,
            'fileId' => $fileId
        ]);

        // Clean up OpenAI file and local transcript
        $this->openAIService->deleteFile($fileId);
        unlink($transcriptFile);

        // Fact-check and correct the script using web search before creating video
        $output->writeln('<info>Fact-checking script against SA current affairs...</info>');
        $correctedScript = $this->openAIService->factCheckAndCorrectScript($script);
        if ($correctedScript !== $script) {
            $this->logger->info('Script corrected after fact-check', [ 'preview' => mb_substr($correctedScript, 0, 160) ]);
        }

        // Apply word replacements before creating video
        $output->writeln('<info>Applying word replacements...</info>');
        $finalScript = $this->applyWordReplacements($correctedScript);
        if ($finalScript !== $correctedScript) {
            $this->logger->info('Script modified with word replacements', [ 'preview' => mb_substr($finalScript, 0, 160) ]);
        }

        // Log final script length
        $finalScriptLength = mb_strlen($finalScript);
        $finalScriptWordCount = str_word_count($finalScript);
        $this->logger->info('Final script length', [
            'characterCount' => $finalScriptLength,
            'wordCount' => $finalScriptWordCount
        ]);

        // Create HeyGen video
        $output->writeln('<info>Creating HeyGen video...</info>');
        $heyGenVideoId = $this->heyGenService->createAvatarVideoFromText(
            $finalScript,
            $avatarId,
            $voiceId,
            1080,
            1920,
            true
        );

        if (!$heyGenVideoId) {
            $output->writeln('<error>Failed to create HeyGen video</error>');
            return Command::FAILURE;
        }

        $output->writeln("HeyGen Video ID: {$heyGenVideoId}");

        // Wait for completion with retry logic
        $output->writeln('<info>Waiting for video completion...</info>');
        $maxRetries = 180; // 30 minutes with 10-second intervals
        $retryCount = 0;
        $videoUrl = null;
        $captionUrl = null;
        
        while ($retryCount < $maxRetries) {
            $status = $this->heyGenService->getVideoStatus($heyGenVideoId);
            
            if (!$status) {
                $output->writeln("<comment>Video status not found, retrying... (attempt {$retryCount}/{$maxRetries})</comment>");
                sleep(10);
                $retryCount++;
                continue;
            }
            
            $videoStatus = $status['status'] ?? 'unknown';
            $output->writeln("<info>Video status: {$videoStatus}</info>");
            
            if ($videoStatus === 'completed') {
                if (isset($status['video_url'])) {
                    $videoUrl = $status['video_url'];
                    $captionUrl = $status['caption_url'] ?? null;
                    $output->writeln("Video completed! URL: {$videoUrl}");
                    if ($captionUrl) {
                        $output->writeln("Caption URL: {$captionUrl}");
                        $this->logger->info('HeyGen caption available', ['caption_url' => $captionUrl]);
                    } else {
                        $output->writeln("<comment>No caption URL provided by HeyGen</comment>");
                    }
                    break;
                } else {
                    $output->writeln("<comment>Video completed but no URL yet, retrying...</comment>");
                }
            } elseif ($videoStatus === 'failed') {
                $errorDetails = $status['error'] ?? 'Unknown error';
                $output->writeln('<error>Video generation failed</error>');
                $output->writeln("<error>Error details: " . json_encode($errorDetails) . "</error>");
                $this->logger->error('HeyGen video failed', ['status' => $status]);
                return Command::FAILURE;
            } elseif (in_array($videoStatus, ['waiting', 'pending', 'processing'])) {
                $statusMessages = [
                    'waiting' => 'Video is in queue',
                    'pending' => 'Video is in queue', 
                    'processing' => 'Video is being rendered'
                ];
                $message = $statusMessages[$videoStatus] ?? $videoStatus;
                $output->writeln("<comment>{$message}, waiting...</comment>");
            } else {
                $output->writeln("<comment>Unknown status ({$videoStatus}), waiting...</comment>");
            }
            
            sleep(10);
            $retryCount++;
        }
        
        if ($retryCount >= $maxRetries) {
            $output->writeln('<error>Video generation timed out after 30 minutes</error>');
            $this->logger->error('HeyGen video timeout', ['video_id' => $heyGenVideoId]);
            return Command::FAILURE;
        }
        
        if (!$videoUrl) {
            $output->writeln('<error>Video completed but no URL available</error>');
            return Command::FAILURE;
        }

        // Post-process: download and optionally burn captions first (using temporary ID)
        $output->writeln('<info>Downloading video and burning captions...</info>');
        $tempId = (int)time();
        $noBurn = (bool)$input->getOption('no-burn');
        $this->burnAndCache($tempId, $videoUrl, $captionUrl, $output, !$noBurn);

        // Create database entry after captions are burnt
        $date = date('Y-m-d H:i');
        $videoCount = count($videos);
        $dbTitle = "SABC Digital News - Last 4 Hours ({$videoCount} videos) - {$date}";

        // Compute YouTube upload title based on time of day
        $dateOnly = date('j F');
        $period = 'Afternoon';
        if (isset($currentHour)) {
            if ($currentHour >= 18) {
                $period = 'Evening';
            } elseif ($currentHour >= 13) {
                $period = 'Afternoon';
            } elseif ($currentHour >= 10) {
                $period = 'Midday';
            } else {
                $period = 'Morning';
            }
        }
        $uploadTitle = $period . ' News Update - ' . $dateOnly;
        
        // Save caption URL if it was provided by HeyGen
        $heyGenVideo = new HeyGenVideo($dbTitle, $videoUrl, false, $captionUrl);

        $this->entityManager->persist($heyGenVideo);
        $this->entityManager->flush();

        // Rename the rendered file to match the database ID
        $publicDir = dirname(__DIR__, 2) . '/public/uploads/documents/heygen/rendered';
        $tempFile = $publicDir . '/' . $tempId . '.mp4';
        $finalFile = $publicDir . '/' . $heyGenVideo->getId() . '.mp4';
        if (is_file($tempFile)) {
            @rename($tempFile, $finalFile);
        }

        $output->writeln('<info>Video saved to database with ID: ' . $heyGenVideo->getId() . '</info>');
        $this->logger->info('SABC Past 4 Hours video created', [
            'heyGenVideoId' => $heyGenVideoId,
            'videoUrl' => $videoUrl,
            'dbId' => $heyGenVideo->getId(),
            'videoCount' => count($videos)
        ]);

        // Optional direct upload to YouTube (YouTube Data API)
        $uploadYouTube = (bool)$input->getOption('upload-youtube');
        if ($uploadYouTube) {
            $privacy = (string)$input->getOption('privacy') ?: 'public';
            $description = 'Welcome to South Africa 60 seconds news — real news, no fluff. Fast, factual, and to the point — updated every four hours at 12:00, 16:00, and 20:00. Stay informed. Stay sharp. 
#SouthAfrica #WhySoSerious #BreakingNews';
            if (!is_file($finalFile)) {
                $output->writeln('<error>Rendered file not found for upload: ' . $finalFile . '</error>');
            } else {
                $output->writeln('<info>Uploading video to YouTube...</info>');
                try {
                    $ytVideoId = $this->uploadVideoToYouTube(
                        filePath: (string)$finalFile,
                        title: (string)$uploadTitle,
                        description: (string)$description,
                        privacyStatus: (string)$privacy,
                        output: $output
                    );
                    if (is_string($ytVideoId) && $ytVideoId !== '') {
                        $output->writeln('<info>YouTube upload successful. Video ID: ' . $ytVideoId . '</info>');
                        $this->logger->info('YouTube upload successful', ['youtubeVideoId' => $ytVideoId]);
                        // Remove local file after successful upload
                        @unlink($finalFile);
                        $output->writeln('<comment>Local rendered video deleted after successful YouTube upload: ' . $finalFile . '</comment>');
                    } else {
                        $output->writeln('<error>YouTube upload did not return a video ID.</error>');
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('YouTube upload failed', ['error' => $e->getMessage()]);
                    $output->writeln('<error>YouTube upload failed: ' . $e->getMessage() . '</error>');
                }
            }
        }

        $output->writeln('<info>SABC Past 4 Hours video created successfully!</info>');
        return Command::SUCCESS;
    }

    private function findVideosFromLastFourHours(int $limit = 20): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.youtube.com/@sabcdigitalnews/videos', [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'timeout' => 20,
            ]);
            $html = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch SABC Digital News videos page', ['error' => $e->getMessage()]);
            return [];
        }

        // Extract JSON data
        $json = $this->extractJsonBetween($html, 'var ytInitialData =', '</script>');
        if ($json === null) {
            $json = $this->extractJsonBetween($html, 'ytInitialData =', '</script>');
        }

        if ($json === null) {
            $this->logger->error('Could not extract ytInitialData from SABC Digital News page');
            return [];
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to parse ytInitialData JSON', ['error' => $e->getMessage()]);
            return [];
        }

        // Search for all videos from the last 4 hours (YouTube already sorts by newest first)
        $queue = [$data];
        $videos = [];
        $totalVideosFound = 0;
        
        $this->logger->info('Starting search for videos from last 4 hours');
        
        while ($queue) {
            $node = array_shift($queue);
            if (!is_array($node)) { continue; }

            // Check various video renderer types
            $candidates = [];
            if (isset($node['videoRenderer']) && is_array($node['videoRenderer'])) { 
                $candidates[] = $node['videoRenderer']; 
            }
            if (isset($node['gridVideoRenderer']) && is_array($node['gridVideoRenderer'])) { 
                $candidates[] = $node['gridVideoRenderer']; 
            }
            if (isset($node['richItemRenderer']['content']['videoRenderer']) && is_array($node['richItemRenderer']['content']['videoRenderer'])) { 
                $candidates[] = $node['richItemRenderer']['content']['videoRenderer']; 
            }
            if (isset($node['richGridMedia']) && is_array($node['richGridMedia'])) {
                $rgm = $node['richGridMedia'];
                $mapped = [
                    'videoId' => $rgm['videoId'] ?? null,
                    'title' => [
                        'runs' => $rgm['title']['runs'] ?? null,
                        'simpleText' => $rgm['title']['simpleText'] ?? null,
                    ],
                    'publishedTimeText' => [
                        'runs' => $rgm['publishedTimeText']['runs'] ?? null,
                        'simpleText' => $rgm['publishedTimeText']['simpleText'] ?? null,
                    ],
                ];
                $candidates[] = $mapped;
            }

            foreach ($candidates as $vr) {
                $totalVideosFound++;
                $titleText = $this->extractTextFromRuns($vr['title']['runs'] ?? null) ?? ($vr['title']['simpleText'] ?? null);
                
                if ($titleText !== null) {
                    // Exclude videos with "Prime News" or "Headlines" in the title
                    $titleLower = strtolower($titleText);
                    if (str_contains($titleLower, 'prime news') || str_contains($titleLower, 'headlines')) {
                        $this->logger->info('Excluding video (Prime News or Headlines)', [
                            'title' => $titleText,
                            'videoId' => $vr['videoId'] ?? 'unknown'
                        ]);
                        continue;
                    }
                    
                    // Check if it's within the last 4 hours
                    $published = $this->extractTextFromRuns($vr['publishedTimeText']['runs'] ?? null) ?? ($vr['publishedTimeText']['simpleText'] ?? null);
                    if ($this->isWithinLastFourHours($published)) {
                        $videoId = $this->sanitizeVideoId($vr['videoId'] ?? '');
                        if ($videoId) {
                            $ageInSeconds = $this->getPublishedAgeInSeconds($published);
                            $videos[] = [
                                'id' => $videoId,
                                'title' => $titleText,
                                'published' => $published,
                                'ageInSeconds' => $ageInSeconds
                            ];
                            $this->logger->info('Found video from last 4 hours', [
                                'videoId' => $videoId,
                                'title' => $titleText,
                                'published' => $published,
                                'ageInSeconds' => $ageInSeconds
                            ]);
                        }
                    }
                }
            }

            // Add children to queue
            foreach ($node as $child) { 
                if (is_array($child)) { 
                    $queue[] = $child; 
                } 
            }
        }
        
        $videosFoundWithinWindow = count($videos);

        if ($videosFoundWithinWindow > 0) {
            usort($videos, static function (array $a, array $b): int {
                $ageA = $a['ageInSeconds'] ?? PHP_INT_MAX;
                $ageB = $b['ageInSeconds'] ?? PHP_INT_MAX;

                return $ageA <=> $ageB;
            });

            if ($videosFoundWithinWindow > $limit) {
                $videos = array_slice($videos, 0, $limit);
            }
        }

        $this->logger->info('SABC Digital News search completed', [
            'totalVideosFound' => $totalVideosFound,
            'videosFromLast4Hours' => $videosFoundWithinWindow,
            'videosReturned' => count($videos),
            'limit' => $limit
        ]);

        return array_map(static function (array $video): array {
            return [
                'id' => $video['id'],
                'title' => $video['title']
            ];
        }, $videos);
    }

    private function burnAndCache(int $id, string $videoUrl, ?string $captionUrl, OutputInterface $output, bool $burnCaptions = true): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public/uploads/documents/heygen/rendered';
        if (!is_dir($publicDir)) { @mkdir($publicDir, 0755, true); }
        $outputFile = $publicDir . '/' . $id . '.mp4';
        if (is_file($outputFile)) { 
            $output->writeln('<info>Rendered file already exists, skipping burn.</info>'); 
            return; 
        }

        $tmpDir = sys_get_temp_dir() . '/heygen_' . $id;
        if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0700, true); }
        $videoTmp = $tmpDir . '/video.mp4';
        $processedVideoTmp = $tmpDir . '/processed.mp4';
        $subsTmp = null; // will be set after download based on extension

        // Path to intro video
        $introVideoPath = dirname(__DIR__, 2) . '/news-intro.mp4';
        if (!is_file($introVideoPath)) {
            $output->writeln('<error>Intro video not found at: ' . $introVideoPath . '</error>');
            return;
        }

        $output->writeln('<info>Downloading video...</info>');
        $this->downloadToFile($videoUrl, $videoTmp);

        $hasCaptions = false;
        if ($burnCaptions && is_string($captionUrl) && $captionUrl !== '') {
            $output->writeln('<info>Downloading captions...</info>');
            $clean = preg_replace('/[?#].*$/', '', $captionUrl);
            $ext = strtolower(pathinfo($clean ?? '', PATHINFO_EXTENSION));
            $ext = $ext ?: 'ass';
            $subsTmp = $tmpDir . '/subs.' . $ext;
            $this->downloadToFile($captionUrl, $subsTmp);

            // Shift subtitle timing to lead the audio slightly (fix small lag)
            // Negative offset means captions appear earlier. Tune as needed.
            $this->shiftSubtitleTiming($subsTmp, -2.0);

            $hasCaptions = is_file($subsTmp) && filesize($subsTmp) > 0;
        }

        // Step 1: Process the generated video (burn captions if available) to an intermediate file
        if ($hasCaptions) {
            $output->writeln('<info>Burning captions into video...</info>');
            $escaped = str_replace(':', '\\:', $subsTmp);
            $force = "Alignment=5,MarginV=150,MarginL=40,MarginR=5,Outline=1,FontSize=20,LineSpacing=2,WrapStyle=0";
            $filter = "subtitles='" . $escaped . "':force_style='" . $force . "'";
            $cmd = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($videoTmp) . ' -vf ' . escapeshellarg($filter) . ' -c:a copy ' . escapeshellarg($processedVideoTmp);
        } else {
            $output->writeln('<comment>No captions available, processing video without captions...</comment>');
            // Copy to processed video temp file
            $cmd = 'cp ' . escapeshellarg($videoTmp) . ' ' . escapeshellarg($processedVideoTmp);
        }
        
        $proc = new Process(['bash', '-lc', $cmd]);
        $proc->setTimeout(600);
        $proc->run();
        if (!$proc->isSuccessful()) {
            $output->writeln('<comment>Video processing failed: ' . $proc->getErrorOutput() . '</comment>');
            return;
        }

        // Step 2: Merge intro video with processed video (intro first)
        $output->writeln('<info>Merging intro video with generated video...</info>');
        
        // Normalize and merge videos using concat demuxer (more reliable than filter_complex)
        // First, normalize both videos to the same format
        $introNormalized = $tmpDir . '/intro_normalized.mp4';
        $processedNormalized = $tmpDir . '/processed_normalized.mp4';
        
        // Normalize intro video: scale to 1080x1920, normalize audio format
        // Handle audio gracefully - use existing audio or create silent audio track
        $normalizeIntroCmd = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($introVideoPath) . 
                           ' -vf "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1" -r 30' .
                           ' -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p' .
                           ' -af "aresample=48000:async=1" -c:a aac -b:a 128k -ar 48000 -ac 2' .
                           ' -shortest ' . escapeshellarg($introNormalized);
        
        $normIntroProc = new Process(['bash', '-lc', $normalizeIntroCmd]);
        $normIntroProc->setTimeout(600);
        $normIntroProc->run();
        
        // If normalization failed (e.g., no audio), try with silent audio
        if (!$normIntroProc->isSuccessful() || !is_file($introNormalized)) {
            $output->writeln('<comment>Intro video normalization with audio failed, trying with silent audio...</comment>');
            $normalizeIntroCmdSilent = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($introVideoPath) . 
                                     ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000' .
                                     ' -vf "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1" -r 30' .
                                     ' -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p' .
                                     ' -c:a aac -b:a 128k -shortest -map 0:v:0 -map 1:a:0 ' . escapeshellarg($introNormalized);
            
            $normIntroProcSilent = new Process(['bash', '-lc', $normalizeIntroCmdSilent]);
            $normIntroProcSilent->setTimeout(600);
            $normIntroProcSilent->run();
            
            if (!$normIntroProcSilent->isSuccessful() || !is_file($introNormalized)) {
                $output->writeln('<error>Failed to normalize intro video: ' . $normIntroProcSilent->getErrorOutput() . '</error>');
                // Fallback: use processed video without intro
                if (is_file($processedVideoTmp)) {
                    @copy($processedVideoTmp, $outputFile);
                    $output->writeln('<comment>Fallback: Using processed video without intro</comment>');
                }
                return;
            }
        }
        
        // Normalize processed video: scale to 1080x1920, normalize audio format
        $normalizeProcessedCmd = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($processedVideoTmp) . 
                               ' -vf "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1" -r 30' .
                               ' -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p' .
                               ' -af "aresample=48000:async=1" -c:a aac -b:a 128k -ar 48000 -ac 2' .
                               ' -shortest ' . escapeshellarg($processedNormalized);
        
        $normProcessedProc = new Process(['bash', '-lc', $normalizeProcessedCmd]);
        $normProcessedProc->setTimeout(600);
        $normProcessedProc->run();
        
        // If normalization failed (e.g., no audio), try with silent audio
        if (!$normProcessedProc->isSuccessful() || !is_file($processedNormalized)) {
            $output->writeln('<comment>Processed video normalization with audio failed, trying with silent audio...</comment>');
            $normalizeProcessedCmdSilent = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($processedVideoTmp) . 
                                         ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000' .
                                         ' -vf "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1" -r 30' .
                                         ' -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p' .
                                         ' -c:a aac -b:a 128k -shortest -map 0:v:0 -map 1:a:0 ' . escapeshellarg($processedNormalized);
            
            $normProcessedProcSilent = new Process(['bash', '-lc', $normalizeProcessedCmdSilent]);
            $normProcessedProcSilent->setTimeout(600);
            $normProcessedProcSilent->run();
            
            if (!$normProcessedProcSilent->isSuccessful() || !is_file($processedNormalized)) {
                $output->writeln('<error>Failed to normalize processed video: ' . $normProcessedProcSilent->getErrorOutput() . '</error>');
                // Fallback: use processed video without intro
                if (is_file($processedVideoTmp)) {
                    @copy($processedVideoTmp, $outputFile);
                    $output->writeln('<comment>Fallback: Using processed video without intro</comment>');
                }
                return;
            }
        }
        
        // Create concat file list (escape single quotes in paths)
        $concatFile = $tmpDir . '/concat_list.txt';
        $introPathEscaped = str_replace("'", "'\\''", $introNormalized);
        $processedPathEscaped = str_replace("'", "'\\''", $processedNormalized);
        $concatContent = "file '{$introPathEscaped}'\n";
        $concatContent .= "file '{$processedPathEscaped}'\n";
        file_put_contents($concatFile, $concatContent);
        
        // Concatenate videos using concat demuxer (fast, no re-encoding)
        $mergeCmd = 'ffmpeg -y -loglevel error -f concat -safe 0 -i ' . escapeshellarg($concatFile) . 
                   ' -c copy ' . escapeshellarg($outputFile);
        
        $mergeProc = new Process(['bash', '-lc', $mergeCmd]);
        $mergeProc->setTimeout(900); // 15 minutes timeout for merging
        $mergeProc->run();
        
        if ($mergeProc->isSuccessful()) {
            $captionStatus = $hasCaptions ? ' with captions burned' : ' (no captions)';
            $output->writeln('<info>Rendered video prepared' . $captionStatus . ' and merged with intro: ' . $outputFile . '</info>');
        } else {
            $output->writeln('<error>Video merge failed: ' . $mergeProc->getErrorOutput() . '</error>');
            // Fallback: if merge fails, just use the processed video without intro
            if (is_file($processedVideoTmp)) {
                @copy($processedVideoTmp, $outputFile);
                $output->writeln('<comment>Fallback: Using processed video without intro</comment>');
            }
        }
    }

    /**
     * Shift subtitle timestamps by a given offset in seconds. Supports ASS, SRT, and VTT.
     */
    private function shiftSubtitleTiming(string $subtitlePath, float $offsetSeconds): void
    {
        if (!is_file($subtitlePath) || !is_readable($subtitlePath)) {
            return;
        }

        $ext = strtolower(pathinfo($subtitlePath, PATHINFO_EXTENSION));
        $content = @file_get_contents($subtitlePath);
        if ($content === false || $content === '') {
            return;
        }

        // Helper formatters
        $formatSrtTime = static function (float $seconds): string {
            if ($seconds < 0) { $seconds = 0.0; }
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = floor($seconds % 60);
            $ms = (int)round(($seconds - floor($seconds)) * 1000);
            return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
        };

        $formatAssTime = static function (float $seconds): string {
            if ($seconds < 0) { $seconds = 0.0; }
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = floor($seconds % 60);
            $cs = (int)round(($seconds - floor($seconds)) * 100); // centiseconds
            return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $cs);
        };

        // Shift logic by type
        if ($ext === 'srt') {
            // 00:00:01,000 --> 00:00:02,000
            $content = preg_replace_callback(
                '/^(\d{2}):(\d{2}):(\d{2}),(\d{3})\s+-->\s+(\d{2}):(\d{2}):(\d{2}),(\d{3})/m',
                function ($m) use ($offsetSeconds, $formatSrtTime) {
                    $start = ((int)$m[1]) * 3600 + ((int)$m[2]) * 60 + (int)$m[3] + ((int)$m[4]) / 1000.0;
                    $end = ((int)$m[5]) * 3600 + ((int)$m[6]) * 60 + (int)$m[7] + ((int)$m[8]) / 1000.0;
                    $start += $offsetSeconds;
                    $end += $offsetSeconds;
                    return $formatSrtTime($start) . ' --> ' . $formatSrtTime($end);
                },
                $content
            );
            @file_put_contents($subtitlePath, $content);
            return;
        }

        if ($ext === 'vtt' || $ext === 'webvtt') {
            // 00:00:01.000 --> 00:00:02.000
            $content = preg_replace_callback(
                '/^(\d{2}):(\d{2}):(\d{2})\.(\d{3})\s+-->\s+(\d{2}):(\d{2}):(\d{2})\.(\d{3})/m',
                function ($m) use ($offsetSeconds) {
                    $fmt = function (float $seconds): string {
                        if ($seconds < 0) { $seconds = 0.0; }
                        $h = floor($seconds / 3600);
                        $m2 = floor(($seconds % 3600) / 60);
                        $s = floor($seconds % 60);
                        $ms = (int)round(($seconds - floor($seconds)) * 1000);
                        return sprintf('%02d:%02d:%02d.%03d', $h, $m2, $s, $ms);
                    };
                    $start = ((int)$m[1]) * 3600 + ((int)$m[2]) * 60 + (int)$m[3] + ((int)$m[4]) / 1000.0;
                    $end = ((int)$m[5]) * 3600 + ((int)$m[6]) * 60 + (int)$m[7] + ((int)$m[8]) / 1000.0;
                    $start += $offsetSeconds;
                    $end += $offsetSeconds;
                    return $fmt($start) . ' --> ' . $fmt($end);
                },
                $content
            );
            @file_put_contents($subtitlePath, $content);
            return;
        }

        // Default: treat as ASS
        // Dialogue: 0,0:00:03.37,0:00:05.69,Default,,0,0,0,,Text
        $content = preg_replace_callback(
            '/^(Dialogue:[^,]*,)(\d+):(\d{2}):(\d{2})\.(\d{2}),(\d+):(\d{2}):(\d{2})\.(\d{2})(,.*)$/m',
            function ($m) use ($offsetSeconds, $formatAssTime) {
                $start = ((int)$m[2]) * 3600 + ((int)$m[3]) * 60 + (int)$m[4] + ((int)$m[5]) / 100.0;
                $end = ((int)$m[6]) * 3600 + ((int)$m[7]) * 60 + (int)$m[8] + ((int)$m[9]) / 100.0;
                $start += $offsetSeconds;
                $end += $offsetSeconds;
                return $m[1] . $formatAssTime($start) . ',' . $formatAssTime($end) . $m[10];
            },
            $content
        );
        @file_put_contents($subtitlePath, $content);
    }

    private function downloadToFile(string $url, string $dest): void
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => 120, 'max_redirects' => 5]);
        $content = $response->getContent();
        file_put_contents($dest, $content);
    }

    private function uploadVideoToYouTube(
        string $filePath,
        string $title,
        string $description,
        string $privacyStatus,
        OutputInterface $output
    ): ?string {
        $clientId = (string)$this->resolveEnv('GOOGLE_CLIENT_ID');
        $clientSecret = (string)$this->resolveEnv('GOOGLE_CLIENT_SECRET');
        $refreshToken = (string)$this->resolveEnv('YOUTUBE_REFRESH_TOKEN');
        $appName = (string)($this->resolveEnv('GOOGLE_APP_NAME') ?: 'ExamQuizBackend');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            $output->writeln('<error>Missing Google OAuth env vars. Required: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, YOUTUBE_REFRESH_TOKEN</error>');
            return null;
        }

        $client = new GoogleClient();
        $client->setApplicationName($appName);
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setAccessType('offline');
        $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
        $client->setPrompt('none');
        $client->setAccessToken([
            'access_token' => '',
            'expires_in' => 0,
            'created' => 0,
            'refresh_token' => $refreshToken,
        ]);

        // Ensure we have a valid access token
        $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if ($client->getAccessToken() === null) {
            $output->writeln('<error>Failed to fetch access token with provided refresh token.</error>');
            return null;
        }

        $youtube = new GoogleYouTube($client);

        $snippet = new GoogleYouTubeVideoSnippet();
        $snippet->setTitle($title);
        $snippet->setDescription($description);
        $snippet->setCategoryId('25'); // News & Politics

        $status = new GoogleYouTubeVideoStatus();
        $status->setPrivacyStatus(in_array($privacyStatus, ['public', 'unlisted', 'private'], true) ? $privacyStatus : 'public');

        $video = new GoogleYouTubeVideo();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $chunkSizeBytes = 8 * 1024 * 1024; // 8MB
        $client->setDefer(true);

        $insertRequest = $youtube->videos->insert('snippet,status', $video);
        $media = new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($filePath));

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            $output->writeln('<error>Could not open file for upload: ' . $filePath . '</error>');
            $client->setDefer(false);
            return null;
        }

        $statusResponse = false;
        while (!$statusResponse && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $statusResponse = $media->nextChunk($chunk);
        }
        fclose($handle);

        $client->setDefer(false);

        if ($statusResponse instanceof GoogleYouTubeVideo) {
            return $statusResponse->getId() ?: null;
        }
        return null;
    }

    private function extractJsonBetween(string $html, string $start, string $end): ?string
    {
        $startPos = strpos($html, $start);
        if ($startPos === false) {
            return null;
        }
        
        $startPos += strlen($start);
        $endPos = strpos($html, $end, $startPos);
        if ($endPos === false) {
            return null;
        }
        
        $json = substr($html, $startPos, $endPos - $startPos);
        $json = trim($json, ' ;');
        
        return $json;
    }

    private function resolveEnv(string $name): string
    {
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return (string)$_SERVER[$name];
        }
        if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
            return (string)$_ENV[$name];
        }
        $val = getenv($name);
        return is_string($val) ? $val : '';
    }

    private function extractTextFromRuns(?array $runs): ?string
    {
        if (!is_array($runs)) {
            return null;
        }
        
        $text = '';
        foreach ($runs as $run) {
            if (isset($run['text'])) {
                $text .= $run['text'];
            }
        }
        
        return $text ?: null;
    }

    private function sanitizeVideoId(string $videoId): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId);
    }

    private function getPublishedAgeInSeconds(?string $published): ?int
    {
        if ($published === null) {
            return null;
        }

        $normalized = strtolower(trim($published));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s*\(.*\)$/', '', $normalized);
        $normalized = preg_replace('/^(streamed|premiered)\s+/', '', $normalized);
        $normalized = preg_replace('/^(streamed|premiered)\s+live\s+/', '', $normalized);

        if (preg_match('/^(\d+)\s+second(s)?\s+ago$/', $normalized, $match)) {
            return (int)$match[1];
        }
        if (preg_match('/^(an|a)\s+second\s+ago$/', $normalized)) {
            return 1;
        }

        if (preg_match('/^(\d+)\s+minute(s)?\s+ago$/', $normalized, $match)) {
            return (int)$match[1] * 60;
        }
        if (preg_match('/^(an|a)\s+minute\s+ago$/', $normalized)) {
            return 60;
        }

        if (preg_match('/^(\d+)\s+hour(s)?\s+ago$/', $normalized, $match)) {
            return (int)$match[1] * 3600;
        }
        if (preg_match('/^(an|a)\s+hour\s+ago$/', $normalized)) {
            return 3600;
        }

        if ($normalized === 'today') {
            return 4 * 3600;
        }

        return null;
    }

    private function isWithinLastFourHours(?string $published): bool
    {
        if ($published === null) {
            return false;
        }
        $lower = strtolower(trim($published));
        
        // Minutes ago - definitely within 4 hours
        if (str_contains($lower, 'minute ago') || str_contains($lower, 'minutes ago')) {
            return true;
        }
        
        // Check hours ago - must be 4 or fewer
        if (preg_match('/^(\d+)\s+hour(s)?\s+ago$/', $lower, $m)) {
            $hours = (int)($m[1] ?? 999);
            return $hours <= 4;
        }
        
        // "1 hour ago" or "an hour ago" - within 4 hours
        if (str_contains($lower, 'hour ago')) {
            return true;
        }
        
        // "today" - could be within 4 hours, but we'll be conservative and check if it's recent
        // Since we can't determine exact time, we'll accept "today" as potentially valid
        if ($lower === 'today') {
            return true;
        }
        
        // Everything else (yesterday, days ago, etc.) is too old
        return false;
    }

    /**
     * Apply word replacements from the database to the script text.
     * Replaces words with their replacement words (case-insensitive, whole word matching).
     */
    private function applyWordReplacements(string $script): string
    {
        $replacements = $this->wordReplacementRepository->findActiveReplacements();
        
        if (empty($replacements)) {
            return $script;
        }

        $result = $script;
        
        foreach ($replacements as $replacement) {
            $word = $replacement->getWord();
            $replacementWord = $replacement->getReplacementWord();
            
            // Use word boundaries to match whole words only (case-insensitive)
            // \b matches word boundaries, and we use the 'i' flag for case-insensitive matching
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
            $result = preg_replace($pattern, $replacementWord, $result);
        }
        
        return $result;
    }
}
