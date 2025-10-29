<?php

namespace App\Command;

use App\Service\SabcDigitalScraper;
use App\Service\YouTubeTranscriptService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:fetch-transcripts',
    description: 'Fetch transcripts for SABC Prime News and EWN "The day that was" videos'
)]
class FetchTranscriptsCommand extends Command
{
    private SabcDigitalScraper $scraper;
    private YouTubeTranscriptService $transcriptService;
    private LoggerInterface $logger;

    public function __construct(
        SabcDigitalScraper $scraper,
        YouTubeTranscriptService $transcriptService,
        LoggerInterface $logger
    ) {
        $this->scraper = $scraper;
        $this->transcriptService = $transcriptService;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days back to search (0=today, 1=yesterday, etc.)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int)$input->getOption('days');
        
        $output->writeln('<info>Fetching transcripts for today...</info>');

        // Find SABC Prime News video
        [$primeId, $primeTitle] = $this->scraper->findPrimeNewsVideoWithinDays($days);
        
        if (!$primeId) {
            $output->writeln('<error>No SABC Prime News video found</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>SABC Prime News found:</info>');
        $output->writeln("  Video ID: {$primeId}");
        $output->writeln("  Title: {$primeTitle}");

        // Find EWN "The day that was" video
        [$ewnId, $ewnTitle] = $this->scraper->findRecentVideoByTitleContains(
            'https://www.youtube.com/@EyewitnessNewsZA/videos', 
            'the day that was', 
            $days
        );

        if (!$ewnId) {
            $output->writeln('<error>No EWN "The day that was" video found</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>EWN "The day that was" found:</info>');
        $output->writeln("  Video ID: {$ewnId}");
        $output->writeln("  Title: {$ewnTitle}");

        // Fetch SABC transcript
        $output->writeln('<info>Fetching SABC transcript...</info>');
        $primeUrl = "https://www.youtube.com/watch?v={$primeId}";
        $primeTranscript = $this->transcriptService->fetchTranscript($primeUrl, 'text', false, false);
        
        if (!is_string($primeTranscript) || trim($primeTranscript) === '') {
            $primeJson = $this->transcriptService->fetchTranscript($primeUrl, 'json', false, false);
            if (is_array($primeJson) && isset($primeJson['segments']) && is_array($primeJson['segments'])) {
                $primeTranscript = trim(implode("\n", array_map(static function ($seg) { 
                    return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : ''; 
                }, $primeJson['segments'])));
            }
        }

        if ($primeTranscript) {
            $output->writeln('<info>SABC transcript length: ' . strlen($primeTranscript) . ' characters</info>');
            $this->logger->info('SABC Prime News transcript', [
                'videoId' => $primeId,
                'title' => $primeTitle,
                'transcript' => $primeTranscript
            ]);
        } else {
            $output->writeln('<error>Failed to fetch SABC transcript</error>');
        }

        // Fetch EWN transcript
        $output->writeln('<info>Fetching EWN transcript...</info>');
        $ewnUrl = "https://www.youtube.com/watch?v={$ewnId}";
        $ewnTranscript = $this->transcriptService->fetchTranscript($ewnUrl, 'text', false, false);
        
        if (!is_string($ewnTranscript) || trim($ewnTranscript) === '') {
            $ewnJson = $this->transcriptService->fetchTranscript($ewnUrl, 'json', false, false);
            if (is_array($ewnJson) && isset($ewnJson['segments']) && is_array($ewnJson['segments'])) {
                $ewnTranscript = trim(implode("\n", array_map(static function ($seg) { 
                    return is_array($seg) && isset($seg['text']) ? (string)$seg['text'] : ''; 
                }, $ewnJson['segments'])));
            }
        }

        if ($ewnTranscript) {
            $output->writeln('<info>EWN transcript length: ' . strlen($ewnTranscript) . ' characters</info>');
            $this->logger->info('EWN "The day that was" transcript', [
                'videoId' => $ewnId,
                'title' => $ewnTitle,
                'transcript' => $ewnTranscript
            ]);
        } else {
            $output->writeln('<error>Failed to fetch EWN transcript</error>');
        }

        $output->writeln('<info>Transcripts fetched and logged successfully!</info>');
        return Command::SUCCESS;
    }
}
