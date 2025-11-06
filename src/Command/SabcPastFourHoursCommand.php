<?php

namespace App\Command;

use App\Entity\HeyGenVideo;
use App\Service\HeyGenService;
use App\Service\OpenAIService;
use App\Service\SabcDigitalScraper;
use App\Service\YouTubeTranscriptService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:sabc-past-four-hours',
    description: 'Create video from SABC Digital News videos published in the last 4 hours'
)]
class SabcPastFourHoursCommand extends Command
{
    private HttpClientInterface $httpClient;
    private SabcDigitalScraper $scraper;
    private YouTubeTranscriptService $transcriptService;
    private OpenAIService $openAIService;
    private HeyGenService $heyGenService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        SabcDigitalScraper $scraper,
        YouTubeTranscriptService $transcriptService,
        OpenAIService $openAIService,
        HeyGenService $heyGenService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->scraper = $scraper;
        $this->transcriptService = $transcriptService;
        $this->openAIService = $openAIService;
        $this->heyGenService = $heyGenService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('avatar-id', null, InputOption::VALUE_OPTIONAL, 'HeyGen Avatar ID', '0d457d33c46049f0b42b538abfc8913b')
            ->addOption('voice-id', null, InputOption::VALUE_OPTIONAL, 'HeyGen Voice ID', 'QOdz6iaNL4YniX0zO8BV')
            ->addOption('no-burn', null, InputOption::VALUE_NONE, 'Do not burn captions into the video (copy original video)')
            ->addOption('upload-youtube', null, InputOption::VALUE_NONE, 'Upload the created video to YouTube via Late API')
            ->addOption('late-account-id', null, InputOption::VALUE_OPTIONAL, 'Late YouTube accountId (overrides env)')
            ->addOption('privacy', null, InputOption::VALUE_OPTIONAL, 'YouTube privacyStatus via Late (public|unlisted|private)', 'public');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $avatarId = $input->getOption('avatar-id');
        $voiceId = $input->getOption('voice-id');

        $output->writeln('<info>Searching for SABC Digital News videos from the last 4 hours...</info>');

        // Find all videos from the last 4 hours
        $videos = $this->findVideosFromLastFourHours();

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
        
        // Generate 60-second script with Dan as anchor using merged transcript
        // Target 78 seconds to compensate for faster TTS reading speed (actual result will be ~60 seconds)
        $output->writeln('<info>Generating 60-second script from merged transcripts...</info>');
        
        // Determine greeting based on time of day (runs at 12pm, 4pm, 8pm)
        $currentHour = (int)date('G'); // 24-hour format (0-23)
        $greeting = 'Hi there, here is your afternoon update.'; // Default for 12pm and 4pm
        if ($currentHour >= 18) { // 6pm or later (covers 8pm)
            $greeting = 'Hi there, here is your evening update.';
        } elseif ($currentHour < 12) { // Before noon (shouldn't happen, but just in case)
            $greeting = 'Hi there, here is your morning update.';
        }
        
        $script = $this->openAIService->generateVideoScriptFromTranscript($transcriptContent, 12, 78, 'Dan', $greeting);

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

        // Create HeyGen video
        $output->writeln('<info>Creating HeyGen video...</info>');
        $heyGenVideoId = $this->heyGenService->createAvatarVideoFromText(
            $correctedScript,
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
        $dateOnly = date('Y-m-d');
        $period = 'Afternoon';
        if (isset($currentHour)) {
            if ($currentHour >= 18) {
                $period = 'Evening';
            } elseif ($currentHour === 12) {
                $period = 'Midday';
            } elseif ($currentHour >= 13 && $currentHour < 18) {
                $period = 'Afternoon';
            } else {
                $period = 'Midday';
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

        // Optional upload to YouTube via Late API
        if ((bool)$input->getOption('upload-youtube')) {
            $output->writeln('<info>Uploading video to YouTube via Late...</info>');
            $privacy = 'public';
            $lateAccountId = $input->getOption('late-account-id') ?: $this->resolveEnv('GETLATE_YOUTUBE_ACCOUNT_ID') ?: $this->resolveEnv('GETLATE_ACCOUNT_ID');
            $apiKey = $this->resolveEnv('GETLATE_API_KEY');

            if (!$apiKey) {
                $output->writeln('<error>GETLATE_API_KEY is not set in environment. Skipping upload.</error>');
            } elseif (!$lateAccountId) {
                $output->writeln('<error>No Late accountId provided (use --late-account-id or env GETLATE_YOUTUBE_ACCOUNT_ID). Skipping upload.</error>');
            } else {
                try {
                    $success = $this->uploadToYouTubeViaLate(
                        apiKey: (string)$apiKey,
                        accountId: (string)$lateAccountId,
                        videoUrl: (string)$videoUrl,
                        title: (string)$uploadTitle,
                        description: 'Welcome to South Africa Why So Serious News — real news, no fluff.
Fast, factual, and to the point — updated every four hours at 12:00, 16:00, and 20:00.

We cover what matters most in South Africa: politics, justice, economy, and breaking stories — all delivered in under a minute.
No drama, no spin — just the headlines you need when you need them.

Stay informed. Stay sharp.

🕛 New updates daily — 12:00 | 16:00 | 20:00
#SouthAfrica #WhySoSerious #BreakingNews',
                        privacyStatus: (string)$privacy,
                        output: $output
                    );
                    if ($success) {
                        if (is_file($finalFile)) {
                            @unlink($finalFile);
                            $output->writeln('<comment>Local rendered video deleted after successful upload: ' . $finalFile . '</comment>');
                            $this->logger->info('Deleted local rendered video after Late upload', ['path' => $finalFile]);
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Late upload failed', ['error' => $e->getMessage()]);
                    $output->writeln('<error>Late upload failed: ' . $e->getMessage() . '</error>');
                }
            }
        }

        $output->writeln('<info>SABC Past 4 Hours video created successfully!</info>');
        return Command::SUCCESS;
    }

    private function findVideosFromLastFourHours(): array
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
                            $videos[] = [
                                'id' => $videoId,
                                'title' => $titleText
                            ];
                            $this->logger->info('Found video from last 4 hours', [
                                'videoId' => $videoId,
                                'title' => $titleText,
                                'published' => $published
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
        
        $this->logger->info('SABC Digital News search completed', [
            'totalVideosFound' => $totalVideosFound,
            'videosFromLast4Hours' => count($videos)
        ]);

        return $videos;
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
        $subsTmp = null; // will be set after download based on extension

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

        if ($hasCaptions) {
            $output->writeln('<info>Burning captions into video...</info>');
            $escaped = str_replace(':', '\\:', $subsTmp);
            $force = "Alignment=5,MarginV=150,MarginL=40,MarginR=5,Outline=1,FontSize=20,LineSpacing=2,WrapStyle=0";
            $filter = "subtitles='" . $escaped . "':force_style='" . $force . "'";
            $cmd = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($videoTmp) . ' -vf ' . escapeshellarg($filter) . ' -c:a copy ' . escapeshellarg($outputFile);
        } else {
            $output->writeln('<comment>No captions available, copying video without captions...</comment>');
            $cmd = 'cp ' . escapeshellarg($videoTmp) . ' ' . escapeshellarg($outputFile);
        }
        
        $proc = new Process(['bash', '-lc', $cmd]);
        $proc->setTimeout(600);
        $proc->run();
        if ($proc->isSuccessful()) {
            $captionStatus = $hasCaptions ? ' with captions burned' : ' (no captions)';
            $output->writeln('<info>Rendered video prepared' . $captionStatus . ': ' . $outputFile . '</info>');
        } else {
            $output->writeln('<comment>Burn step failed: ' . $proc->getErrorOutput() . '</comment>');
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

    /**
     * Upload the produced video to YouTube via Late API.
     */
    private function uploadToYouTubeViaLate(
        string $apiKey,
        string $accountId,
        string $videoUrl,
        string $title,
        string $description,
        string $privacyStatus,
        OutputInterface $output
    ): bool {
        $endpoint = 'https://getlate.dev/api/v1/posts';
        $body = [
            'platforms' => [[
                'platform' => 'youtube',
                'accountId' => $accountId,
                'platformSpecificData' => [
                    'privacyStatus' => $privacyStatus,
                    'title' => $title,
                    'description' => $description,
                ],
            ]],
            'content' => $title,
            'mediaItems' => [[
                'type' => 'video',
                'url' => $videoUrl,
            ]],
        ];

        try {
            $resp = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $body,
                'timeout' => 60,
            ]);

            $statusCode = $resp->getStatusCode();
            $data = null;
            try { $data = $resp->toArray(false); } catch (\Throwable $e) { /* ignore */ }

            if ($statusCode >= 200 && $statusCode < 300) {
                $output->writeln('<info>Late upload request accepted.</info>');
                if (is_array($data)) {
                    $preview = json_encode($data);
                    $this->logger->info('Late upload success', ['response' => $data]);
                    if ($preview !== false) {
                        $output->writeln('<comment>Late response: ' . mb_strimwidth($preview, 0, 300, '...') . '</comment>');
                    }
                }
                return true;
            } else {
                $this->logger->error('Late upload error', ['status' => $statusCode, 'response' => $data]);
                $output->writeln('<error>Late upload failed with status ' . $statusCode . '</error>');
                return false;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Late upload exception', ['error' => $e->getMessage()]);
            return false;
        }
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
}
