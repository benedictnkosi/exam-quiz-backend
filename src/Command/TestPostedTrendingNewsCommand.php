<?php

namespace App\Command;

use App\Repository\PostedTrendingNewsRepository;
use App\Service\TwitterNewsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-posted-trending-news',
    description: 'Test the posted trending news tracking system'
)]
class TestPostedTrendingNewsCommand extends Command
{
    public function __construct(
        private readonly TwitterNewsService $twitterNewsService,
        private readonly PostedTrendingNewsRepository $postedTrendingNewsRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('scope', 's', InputOption::VALUE_OPTIONAL, 'Scope to test (global, africa, usa, etc.)', 'global')
            ->addOption('politician', 'p', InputOption::VALUE_OPTIONAL, 'Politician name to test', 'John Doe')
            ->addOption('summary', null, InputOption::VALUE_OPTIONAL, 'Twitter summary to test', '🚨 John Doe accused of corruption in latest scandal #CorruptionNews #GlobalTrending')
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform (check, generate, post, stats)', 'check')
            ->setHelp('This command tests the posted trending news tracking system');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scope = $input->getOption('scope');
        $politician = $input->getOption('politician');
        $summary = $input->getOption('summary');
        $action = $input->getOption('action');

        $io->title('Posted Trending News Tracking System Test');
        $io->text("Scope: {$scope}");
        $io->text("Action: {$action}");

        try {
            switch ($action) {
                case 'check':
                    return $this->testPoliticianCheck($io, $politician, $scope);
                
                case 'generate':
                    return $this->testGenerateNews($io, $scope);
                
                case 'post':
                    return $this->testPostSummary($io, $summary, $scope);
                
                case 'stats':
                    return $this->testStatistics($io, $scope);
                
                default:
                    $io->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function testPoliticianCheck(SymfonyStyle $io, string $politician, string $scope): int
    {
        $io->section('Testing Politician Check');
        
        $hasBeenPosted = $this->postedTrendingNewsRepository->hasPoliticianBeenPostedRecently($politician, $scope);
        
        $io->text("Politician: {$politician}");
        $io->text("Scope: {$scope}");
        $io->text("Has been posted recently: " . ($hasBeenPosted ? 'YES' : 'NO'));
        
        if ($hasBeenPosted) {
            $io->warning("Politician {$politician} has been posted recently in scope {$scope}");
        } else {
            $io->success("Politician {$politician} has not been posted recently in scope {$scope}");
        }
        
        return Command::SUCCESS;
    }

    private function testGenerateNews(SymfonyStyle $io, string $scope): int
    {
        $io->section('Testing News Generation');
        
        $io->text("Generating trending news for scope: {$scope}");
        $result = $this->twitterNewsService->generateTrendingNewsForTwitter($scope);
        
        if ($result['success']) {
            $io->success('News generated successfully');
            $io->text("Generated summaries: " . $result['totalCount']);
            
            foreach ($result['summaries'] as $index => $summary) {
                $io->text("Summary " . ($index + 1) . ":");
                $io->text("  Content: " . $summary['twitterSummary']);
                $io->text("  Characters: " . $summary['characterCount']);
                $io->text("  Politician: " . ($summary['politicianName'] ?? 'None'));
                $io->text("  Country: " . ($summary['country'] ?? 'Unknown'));
                $io->text("  Impact: " . ($summary['impact'] ?? 'medium'));
                $io->newLine();
            }
        } else {
            $io->error('Failed to generate news: ' . $result['error']);
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    private function testPostSummary(SymfonyStyle $io, string $summary, string $scope): int
    {
        $io->section('Testing Summary Posting');
        
        $io->text("Testing summary: {$summary}");
        $io->text("Scope: {$scope}");
        
        $result = $this->twitterNewsService->postTwitterSummary($summary, $scope);
        
        if ($result['success']) {
            $io->success('Summary posted successfully');
            $io->text("Tweet ID: " . ($result['tweetId'] ?? 'N/A'));
            $io->text("Tracking ID: " . ($result['trackingId'] ?? 'N/A'));
            $io->text("Politician: " . ($result['politicianName'] ?? 'None detected'));
        } else {
            $io->error('Failed to post summary: ' . $result['error']);
            if (isset($result['reason'])) {
                $io->text("Reason: " . $result['reason']);
            }
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    private function testStatistics(SymfonyStyle $io, string $scope): int
    {
        $io->section('Testing Statistics');
        
        $startDate = new \DateTime();
        $startDate->modify('-30 days');
        $endDate = new \DateTime();
        
        $postedNews = $this->postedTrendingNewsRepository->findByScopeAndDateRange($scope, $startDate, $endDate);
        
        $io->text("Scope: {$scope}");
        $io->text("Date range: " . $startDate->format('Y-m-d') . " to " . $endDate->format('Y-m-d'));
        $io->text("Total posts: " . count($postedNews));
        
        $politiciansPosted = [];
        $successfulPosts = 0;
        
        foreach ($postedNews as $post) {
            if ($post->getPoliticianName()) {
                $politiciansPosted[] = $post->getPoliticianName();
            }
            if ($post->getTweetId()) {
                $successfulPosts++;
            }
        }
        
        $uniquePoliticians = array_unique($politiciansPosted);
        
        $io->text("Successful posts: {$successfulPosts}");
        $io->text("Failed posts: " . (count($postedNews) - $successfulPosts));
        $io->text("Unique politicians: " . count($uniquePoliticians));
        
        if (!empty($uniquePoliticians)) {
            $io->text("Politicians posted:");
            foreach ($uniquePoliticians as $politician) {
                $io->text("  - {$politician}");
            }
        }
        
        $successRate = count($postedNews) > 0 ? round(($successfulPosts / count($postedNews)) * 100, 2) : 0;
        $io->text("Success rate: {$successRate}%");
        
        return Command::SUCCESS;
    }
} 