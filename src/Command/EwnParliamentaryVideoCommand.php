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
    name: 'app:ewn-parliamentary-video',
    description: 'Create video from EWN Parliamentary ad hoc stream'
)]
class EwnParliamentaryVideoCommand extends Command
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
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days back to search (0=today, 1=yesterday, etc.)', 7)
            ->addOption('avatar-id', null, InputOption::VALUE_OPTIONAL, 'HeyGen Avatar ID', '0d457d33c46049f0b42b538abfc8913b')
            ->addOption('voice-id', null, InputOption::VALUE_OPTIONAL, 'HeyGen Voice ID', 'QOdz6iaNL4YniX0zO8BV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int)$input->getOption('days');
        $avatarId = $input->getOption('avatar-id');
        $voiceId = $input->getOption('voice-id');

        $output->writeln('<info>Searching for Parliamentary ad hoc videos...</info>');

        // Find Parliamentary ad hoc video
        [$videoId, $title] = $this->findParliamentaryAdHocVideo($days);

        if (!$videoId) {
            $output->writeln('<error>No Parliamentary ad hoc video found</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Found video:</info>');
        $output->writeln("  Video ID: {$videoId}");
        $output->writeln("  Title: {$title}");

        // Fetch transcript
        $output->writeln('<info>Fetching transcript...</info>');
        $videoUrl = "https://www.youtube.com/watch?v={$videoId}";
        $transcript = $this->transcriptService->fetchTranscript($videoUrl, 'text', false, false);

        if (!is_string($transcript) || trim($transcript) === '') {
            $json = $this->transcriptService->fetchTranscript($videoUrl, 'json', false, false);
            if (is_array($json) && isset($json['segments']) && is_array($json['segments'])) {
                $transcript = trim(implode("\n", array_map(static function ($seg) { 
                    return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : ''; 
                }, $json['segments'])));
            }
        }

        if (!$transcript) {
            $output->writeln('<error>Failed to fetch transcript</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Transcript length: ' . strlen($transcript) . ' characters</info>');

        // Save transcript to file and upload to OpenAI
        $output->writeln('<info>Saving transcript to file...</info>');
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
        
        $transcriptFile = $transcriptDir . '/parliamentary_' . $videoId . '_' . date('Y-m-d_H-i-s') . '.txt';
        $result = @file_put_contents($transcriptFile, $transcript);
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
        
        $output->writeln('<info>Uploading transcript to OpenAI...</info>');
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
        
        // Generate 60-second script with Sam as anchor using full transcript
        $output->writeln('<info>Generating 60-second script...</info>');
        $script = $this->openAIService->generateVideoScriptFromTranscript($transcriptContent, 3, 60, 'Sam');

        if (!$script) {
            $output->writeln('<error>Failed to generate script</error>');
            $this->openAIService->deleteFile($fileId);
            unlink($transcriptFile);
            return Command::FAILURE;
        }

        $output->writeln('<info>Script generated successfully</info>');
        $this->logger->info('Parliamentary ad hoc script', [
            'videoId' => $videoId,
            'title' => $title,
            'script' => $script,
            'fileId' => $fileId
        ]);

        // Clean up OpenAI file and local transcript
        $this->openAIService->deleteFile($fileId);
        unlink($transcriptFile);

        // Create HeyGen video
        $output->writeln('<info>Creating HeyGen video...</info>');
        $heyGenVideoId = $this->heyGenService->createAvatarVideoFromText(
            $script,
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
        $maxRetries = 60; // 10 minutes with 10-second intervals
        $retryCount = 0;
        
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
            $output->writeln('<error>Video generation timed out after 10 minutes</error>');
            $this->logger->error('HeyGen video timeout', ['video_id' => $heyGenVideoId]);
            return Command::FAILURE;
        }
        
        if (!isset($videoUrl)) {
            $output->writeln('<error>Video completed but no URL available</error>');
            return Command::FAILURE;
        }

        // Create database entry
        $date = date('Y-m-d');
        $dbTitle = "Parliamentary ad hoc {$date}";
        
        // If caption URL was provided by HeyGen, save it too
        $heyGenVideo = new HeyGenVideo($dbTitle, $videoUrl, false, $status['caption_url'] ?? null);

        $this->entityManager->persist($heyGenVideo);
        $this->entityManager->flush();

        $output->writeln('<info>Video saved to database with ID: ' . $heyGenVideo->getId() . '</info>');
        $this->logger->info('Parliamentary ad hoc video created', [
            'heyGenVideoId' => $heyGenVideoId,
            'videoUrl' => $videoUrl,
            'dbId' => $heyGenVideo->getId()
        ]);

        // Post-process: download and burn captions now
        $this->burnAndCache((int)$heyGenVideo->getId(), $videoUrl, $status['caption_url'] ?? null, $output);

        $output->writeln('<info>Parliamentary ad hoc video created successfully!</info>');
        return Command::SUCCESS;
    }

    private function burnAndCache(int $id, string $videoUrl, ?string $captionUrl, OutputInterface $output): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public/uploads/documents/heygen/rendered';
        if (!is_dir($publicDir)) { @mkdir($publicDir, 0755, true); }
        $outputFile = $publicDir . '/' . $id . '.mp4';
        if (is_file($outputFile)) { $output->writeln('<info>Rendered file already exists, skipping burn.</info>'); return; }

        $tmpDir = sys_get_temp_dir() . '/heygen_' . $id;
        if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0700, true); }
        $videoTmp = $tmpDir . '/video.mp4';
        $subsTmp = $tmpDir . '/subs.ass';

        // Download video
        $this->downloadToFile($videoUrl, $videoTmp);

        // Download captions if available
        if (is_string($captionUrl) && $captionUrl !== '') {
            $clean = preg_replace('/[?#].*$/', '', $captionUrl);
            $ext = strtolower(pathinfo($clean ?? '', PATHINFO_EXTENSION));
            $subsPath = $tmpDir . '/subs.' . ($ext ?: 'ass');
            $this->downloadToFile($captionUrl, $subsPath);
            @rename($subsPath, $subsTmp);
        }

        if (is_file($subsTmp) && filesize($subsTmp) > 0) {
            $escaped = str_replace(':', '\\:', $subsTmp);
            $force = "Alignment=5,MarginV=150,MarginL=40,MarginR=5,Outline=1,FontSize=20,LineSpacing=2,WrapStyle=0";
            $filter = "subtitles='" . $escaped . "':force_style='" . $force . "'";
            $cmd = 'ffmpeg -y -loglevel error -i ' . escapeshellarg($videoTmp) . ' -vf ' . escapeshellarg($filter) . ' -c:a copy ' . escapeshellarg($outputFile);
        } else {
            $cmd = 'cp ' . escapeshellarg($videoTmp) . ' ' . escapeshellarg($outputFile);
        }
        $proc = new Process(['bash', '-lc', $cmd]);
        $proc->setTimeout(600);
        $proc->run();
        if ($proc->isSuccessful()) {
            $output->writeln('<info>Rendered video prepared: ' . $outputFile . '</info>');
        } else {
            $output->writeln('<comment>Burn step failed: ' . $proc->getErrorOutput() . '</comment>');
        }
    }

    private function downloadToFile(string $url, string $dest): void
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => 120, 'max_redirects' => 5]);
        $content = $response->getContent();
        file_put_contents($dest, $content);
    }

    private function findParliamentaryAdHocVideo(int $daysBack): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.youtube.com/@EyewitnessNewsZA/streams', [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'timeout' => 20,
            ]);
            $html = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch EWN streams page', ['error' => $e->getMessage()]);
            return [null, null];
        }

        // Extract JSON data
        $json = $this->extractJsonBetween($html, 'var ytInitialData =', '</script>');
        if ($json === null) {
            $json = $this->extractJsonBetween($html, 'ytInitialData =', '</script>');
        }

        if ($json === null) {
            $this->logger->error('Could not extract ytInitialData from EWN streams page');
            return [null, null];
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to parse ytInitialData JSON', ['error' => $e->getMessage()]);
            return [null, null];
        }

        // Search for Parliamentary ad hoc videos (YouTube already sorts by newest first)
        $needle = 'parliamentary ad hoc';
        $queue = [$data];
        $totalVideosFound = 0;
        $matchingVideosFound = 0;
        $dateFilteredVideos = 0;
        
        $this->logger->info('Starting Parliamentary ad hoc video search', [
            'searchTerm' => $needle,
            'daysBack' => $daysBack
        ]);
        
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

            foreach ($candidates as $vr) {
                $totalVideosFound++;
                $titleText = $this->extractTextFromRuns($vr['title']['runs'] ?? null) ?? ($vr['title']['simpleText'] ?? null);
                
                if ($titleText !== null && str_contains(strtolower($titleText), $needle)) {
                    $matchingVideosFound++;
                    $this->logger->info('Found matching video', [
                        'title' => $titleText,
                        'videoId' => $vr['videoId'] ?? 'unknown'
                    ]);

                    // Check if it's within the specified days
                    $published = $this->extractTextFromRuns($vr['publishedTimeText']['runs'] ?? null) ?? ($vr['publishedTimeText']['simpleText'] ?? null);
                    if ($this->looksLikeWithinDays($published, $daysBack)) {
                        $videoId = $this->sanitizeVideoId($vr['videoId'] ?? '');
                        if ($videoId) {
                            $this->logger->info('Found valid video', [
                                'videoId' => $videoId,
                                'title' => $titleText,
                                'published' => $published
                            ]);
                            // Return the first video found (newest due to YouTube's sorting)
                            return [$videoId, $titleText];
                        }
                    } else {
                        $dateFilteredVideos++;
                        $this->logger->info('Video filtered by date', [
                            'title' => $titleText,
                            'published' => $published,
                            'daysBack' => $daysBack
                        ]);
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
        
        $this->logger->info('Parliamentary ad hoc search completed', [
            'totalVideosFound' => $totalVideosFound,
            'matchingVideosFound' => $matchingVideosFound,
            'dateFilteredVideos' => $dateFilteredVideos
        ]);

        return [null, null];
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

    private function looksLikeWithinDays(?string $published, int $daysBack): bool
    {
        if ($published === null) {
            return false;
        }
        $lower = strtolower(trim($published));
        // Hours/minutes ago => 0 days
        if (str_contains($lower, 'hour ago') || str_contains($lower, 'hours ago') || str_contains($lower, 'minute ago') || str_contains($lower, 'minutes ago')) {
            return 0 <= $daysBack;
        }
        if ($lower === 'today') {
            return 0 <= $daysBack;
        }
        if ($lower === 'yesterday') {
            return 1 <= $daysBack;
        }
        if (preg_match('/^(\d+)\s+day(s)?\s+ago$/', $lower, $m)) {
            $days = (int)($m[1] ?? 999);
            return $days <= $daysBack;
        }
        // Unknown wording => be conservative
        return false;
    }
}
