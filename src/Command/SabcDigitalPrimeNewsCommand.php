<?php

namespace App\Command;

use App\Service\SabcDigitalScraper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\HeyGenVideo;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Process\Process;
use App\Service\OpenAIService;

#[AsCommand(
    name: 'app:sabcdigital:scrape-prime-news',
    description: 'Scrape SABC Digital News channel for today\'s Prime News video ID and title, and log them.'
)]
class SabcDigitalPrimeNewsCommand extends Command
{
    use PrimeNewsBurnHelpers;
    private SabcDigitalScraper $scraper;
    private LoggerInterface $logger;
    private \App\Service\YouTubeTranscriptService $transcriptService;
    private \App\Service\HeyGenService $heyGenService;
    private string $defaultAvatarId = '93bf36d167184854bdde4ffb3b340981';
    private string $defaultVoiceId = 'QOdz6iaNL4YniX0zO8BV';
    private EntityManagerInterface $em;
    private HttpClientInterface $httpClient;
    private OpenAIService $openAIService;

    public function __construct(SabcDigitalScraper $scraper, LoggerInterface $logger, \App\Service\YouTubeTranscriptService $transcriptService, \App\Service\HeyGenService $heyGenService, EntityManagerInterface $em, HttpClientInterface $httpClient, OpenAIService $openAIService)
    {
        parent::__construct();
        $this->scraper = $scraper;
        $this->logger = $logger;
        $this->transcriptService = $transcriptService;
        $this->heyGenService = $heyGenService;
        $this->em = $em;
        $this->httpClient = $httpClient;
        $this->openAIService = $openAIService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int)($input->getOption('days') ?? 0);
        if ($days < 0) {
            $days = 0;
        }
        $daysLabel = $days === 0 ? "today's" : ($days === 1 ? "yesterday's" : "the last {$days} days");
        $output->writeln('<info>Searching for ' . $daysLabel . ' Prime News video...</info>');

        $htmlFile = $input->getOption('html-file');
        if (is_string($htmlFile) && $htmlFile !== '' && file_exists($htmlFile)) {
            $output->writeln('<comment>Using HTML file: ' . $htmlFile . '</comment>');
            $html = @file_get_contents($htmlFile) ?: '';
            [$videoId, $title] = $this->scraper->findPrimeNewsVideoWithinDaysFromHtml($html, $days);
        } else {
        $combineEwn = (bool)$input->getOption('combine-ewn');

        if ($combineEwn) {
            $output->writeln('<info>Combining with EWN "The day that was"...</info>');
        }

        [$videoId, $title] = $this->scraper->findPrimeNewsVideoWithinDays($days);
        }

        if ($videoId) {
            $message = sprintf('SABC Prime News for %s -> ID: %s | Title: %s', $daysLabel, $videoId, $title ?? '(unknown)');
            $this->logger->info($message, ['channel' => 'app']);
            $output->writeln('<info>' . $message . '</info>');

            // Fetch transcript as plain text and log full content
            $videoUrl = strlen($videoId) === 11 ? 'https://www.youtube.com/watch?v=' . $videoId : $videoId;
            $transcriptText = $this->transcriptService->fetchTranscript($videoUrl, 'text', false, false);
            if (is_string($transcriptText) && $transcriptText !== '') {
                $this->logger->info('Transcript (text)', [
                    'videoId' => $videoId,
                    'title' => $title,
                    'transcript' => $transcriptText,
                ]);
                $output->writeln('<info>Transcript logged (' . strlen($transcriptText) . ' chars).</info>');
            } else {
                // Fallback: try JSON and join segments
                $transcriptJson = $this->transcriptService->fetchTranscript($videoUrl, 'json', false, false);
                if (is_array($transcriptJson) && isset($transcriptJson['segments']) && is_array($transcriptJson['segments'])) {
                    $joined = trim(implode("\n", array_map(static function ($seg) {
                        return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : '';
                    }, $transcriptJson['segments'])));
                    $this->logger->info('Transcript (json-joined)', [
                        'videoId' => $videoId,
                        'title' => $title,
                        'transcript' => $joined,
                    ]);
                    $output->writeln('<info>Transcript logged from JSON (' . strlen($joined) . ' chars).</info>');
                } else {
                    $this->logger->warning('Transcript not available or API error');
                    $output->writeln('<comment>Transcript not available or API error.</comment>');
                }
            }

            // Generate satirical news script
            $script = $combineEwn
                ? $this->scraper->createCombinedNewsScript($days)
                : $this->scraper->createNewsScriptForVideo($videoId, $title);
            if (is_string($script) && trim($script) !== '') {
                $this->logger->info('News script', [
                    'videoId' => $videoId,
                    'title' => $title,
                    'script' => $script,
                ]);
                $preview = mb_substr($script, 0, 240);
                $output->writeln('<info>News script generated and logged. Preview:</info>');
                $output->writeln($preview . (mb_strlen($script) > 240 ? '…' : ''));

                // Fact-check and correct the script against SA current affairs before video creation
                $output->writeln('<info>Fact-checking script against SA current affairs...</info>');
                $correctedScript = $this->openAIService->factCheckAndCorrectScript($script);
                if ($correctedScript !== $script) {
                    $this->logger->info('Script corrected after fact-check', ['preview' => mb_substr($correctedScript, 0, 160)]);
                }

                // Generate HeyGen avatar video from the corrected script with provided defaults
                $videoIdHeyGen = $this->heyGenService->createAvatarVideoFromText(
                    $correctedScript,
                    $this->defaultAvatarId,
                    $this->defaultVoiceId,
                    1080,
                     1920,
                    true
                );
                if (is_string($videoIdHeyGen)) {
                    $this->logger->info('HeyGen video submitted', ['heygen_video_id' => $videoIdHeyGen]);
                    $output->writeln('<info>HeyGen video submitted: ' . $videoIdHeyGen . '</info>');
                    // Wait for completion and download when done
                    $status = $this->heyGenService->waitForCompletion($videoIdHeyGen, 600, 5);
                    if (is_array($status)) {
                        $this->logger->info('HeyGen status', $status);
                        $output->writeln('<info>HeyGen status: ' . ($status['status'] ?? 'unknown') . '</info>');
                        if (($status['status'] ?? '') === 'completed') {
                            $videoUrl = $status['video_url'] ?? null;
                            $captionUrl = $status['caption_url'] ?? null;
                            $output->writeln('<info>Video URL: ' . ($videoUrl ?? 'n/a') . '</info>');
                            if ($captionUrl) {
                                $output->writeln('<info>Caption URL: ' . $captionUrl . '</info>');
                                $this->logger->info('HeyGen caption available', ['caption_url' => $captionUrl]);
                            }
                            if (is_string($videoUrl) && $videoUrl !== '') {
                                // Save to DB with uploaded=false and caption url
                                $entity = new HeyGenVideo($title ?? 'Prime News', $videoUrl, false, $captionUrl);
                                $this->em->persist($entity);
                                $this->em->flush();

                                // Post-process: download and burn captions now
                                $this->burnAndCache((int)$entity->getId(), $videoUrl, $captionUrl ?? null, $output);
                            }
                        }
                    }
                } else {
                    $this->logger->warning('Failed to create HeyGen video');
                    $output->writeln('<comment>Failed to create HeyGen video.</comment>');
                }
            } else {
                $this->logger->warning('News script generation failed');
                $output->writeln('<comment>News script generation failed.</comment>');
            }

            return Command::SUCCESS;
        }

        $this->logger->warning('No Prime News video found within window on SABC Digital News.', ['days' => $days]);
        $output->writeln('<comment>No Prime News video found for ' . $daysLabel . ' window.</comment>');
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days back to search (0=today, 1=yesterday, etc.)', 0)
            ->addOption('html-file', null, InputOption::VALUE_OPTIONAL, 'Path to a saved HTML file to parse instead of fetching')
            ->addOption('combine-ewn', null, InputOption::VALUE_NONE, 'Combine with EWN "The day that was" transcript before generating script');
    }
}

// Helpers
namespace App\Command;

use Symfony\Component\Console\Output\OutputInterface;

trait PrimeNewsBurnHelpers
{
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

        $this->downloadToFile($videoUrl, $videoTmp);

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
        $proc = new \Symfony\Component\Process\Process(['bash', '-lc', $cmd]);
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
}


