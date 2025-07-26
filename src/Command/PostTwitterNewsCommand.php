<?php

namespace App\Command;

use App\Service\TwitterNewsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:post-twitter-news',
    description: 'Generate global trending news summaries and post them to Twitter',
)]
class PostTwitterNewsCommand extends Command
{
    public function __construct(
        private readonly TwitterNewsService $twitterNewsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Generate summaries without posting to Twitter'
            )
            ->addOption(
                'use-existing',
                'x',
                InputOption::VALUE_NONE,
                'Use existing global news instead of generating new ones'
            )
            ->addOption(
                'single',
                's',
                InputOption::VALUE_NONE,
                'Post only one summary instead of all'
            )
            ->addArgument(
                'scope',
                InputArgument::OPTIONAL,
                'Geographic scope for news (global, africa, usa, europe, asia, etc.)',
                'global'
            )
            ->setHelp('This command generates Twitter summaries from trending news and posts them to Twitter.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $useExisting = $input->getOption('use-existing');
        $single = $input->getOption('single');
        $scope = $input->getArgument('scope');

        $io->title('Twitter News Posting Command');
        $io->text("Generating and posting {$scope} trending news summaries to Twitter...");

        try {
            if ($dryRun) {
                $io->note('DRY RUN MODE - No tweets will be posted');
            }

            if ($useExisting) {
                $io->text("Using existing {$scope} news...");
                $result = $this->twitterNewsService->getLatestNews($scope);
            } else {
                $io->text("Generating fresh {$scope} trending news for Twitter...");
                $result = $this->twitterNewsService->generateTrendingNewsForTwitter($scope);
            }

            if (!$result['success']) {
                $io->error('Failed to get/generate news: ' . ($result['error'] ?? 'Unknown error'));
                return Command::FAILURE;
            }

            $summaries = $result['summaries'];
            $totalCount = count($summaries);

            if ($totalCount === 0) {
                $io->warning('No summaries generated. No news available or all summaries were invalid.');
                return Command::SUCCESS;
            }

            $io->success("Generated {$totalCount} Twitter summaries");

            // Display summaries
            $io->section('Generated Summaries:');
            foreach ($summaries as $index => $summaryData) {
                $io->text("<info>Summary " . ($index + 1) . ":</info>");
                $io->text($summaryData['twitterSummary']);
                $io->text("Characters: " . $summaryData['characterCount']);
                $io->newLine();
            }

            if ($dryRun) {
                $io->success('Dry run completed. No tweets were posted.');
                return Command::SUCCESS;
            }

            // Post to Twitter
            $io->section('Posting to Twitter...');

            if ($single && $totalCount > 0) {
                // Post only the first summary
                $firstSummary = $summaries[0];
                $io->text('Posting single summary...');
                
                $postResult = $this->twitterNewsService->postTwitterSummary($firstSummary['twitterSummary']);
                
                if ($postResult['success']) {
                    $io->success('Tweet posted successfully!');
                    $io->text('Tweet ID: ' . ($postResult['tweetId'] ?? 'N/A'));
                    $io->text('Summary: ' . $firstSummary['twitterSummary']);
                } else {
                    $io->error('Failed to post tweet: ' . ($postResult['error'] ?? 'Unknown error'));
                    return Command::FAILURE;
                }
            } else {
                // Post all summaries
                $io->text('Posting all summaries...');
                
                $postResult = $this->twitterNewsService->generateAndPostAllSummaries($scope);
                
                if (!$postResult['success']) {
                    $io->error('Failed to post tweets: ' . ($postResult['error'] ?? 'Unknown error'));
                    return Command::FAILURE;
                }

                $postedCount = $postResult['totalPosted'];
                $failedCount = $postResult['totalFailed'];

                if ($postedCount > 0) {
                    $io->success("Successfully posted {$postedCount} tweets!");
                    
                    if ($failedCount > 0) {
                        $io->warning("Failed to post {$failedCount} tweets");
                        
                        foreach ($postResult['failedTweets'] as $failedTweet) {
                            $io->text('Failed: ' . substr($failedTweet['summary'], 0, 50) . '...');
                            $io->text('Error: ' . $failedTweet['error']);
                        }
                    }
                } else {
                    $io->error('No tweets were posted successfully');
                    return Command::FAILURE;
                }
            }

            $io->success('Twitter news posting completed!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 