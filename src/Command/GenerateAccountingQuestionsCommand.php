<?php

namespace App\Command;

use App\Service\AccountingQuestionGeneratorService;
use App\Repository\AccountingTopicRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-accounting-questions',
    description: 'Generate accounting questions using AI'
)]
class GenerateAccountingQuestionsCommand extends Command
{
    public function __construct(
        private AccountingQuestionGeneratorService $generatorService,
        private AccountingTopicRepository $accountingTopicRepository,
        private \App\Repository\AccountingQuestionRepository $accountingQuestionRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('mainTopic', InputArgument::OPTIONAL, 'The main topic (optional - if not provided, will generate for all topics)')
            ->addArgument('topic', InputArgument::OPTIONAL, 'The accounting topic (optional - if not provided, will generate for all topics)')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of questions to generate', 1)
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Question type', 'tap-to-select')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be generated without saving')
            ->addOption('all-topics', null, InputOption::VALUE_NONE, 'Generate questions for all available topics')
            ->setHelp('This command generates accounting questions using AI for all levels. Examples: 
- app:generate-accounting-questions "Financial Statements - Income Statement" "Financial Statements" --type="multi-step"
- app:generate-accounting-questions --all-topics (generates for all topics)
- app:generate-accounting-questions (generates for all topics)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $topic = $input->getArgument('topic');
        $mainTopic = $input->getArgument('mainTopic');
        $count = (int) $input->getOption('count');
        $questionType = $input->getOption('type');
        $dryRun = $input->getOption('dry-run');
        $allTopics = $input->getOption('all-topics');

        // Determine if we should generate for all topics
        $generateForAllTopics = $allTopics || (!$topic && !$mainTopic);

        if ($generateForAllTopics) {
            return $this->generateForAllTopics($io, $count, $questionType, $dryRun);
        } else {
            return $this->generateForSpecificTopic($io, $topic, $mainTopic, $count, $questionType, $dryRun);
        }
    }

    private function generateForAllTopics(SymfonyStyle $io, int $count, string $questionType, bool $dryRun): int
    {
        $io->title('AI Accounting Question Generator - All Topics');
        $io->section('Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Mode', 'ALL TOPICS'],
                ['Levels', 'ALL LEVELS'],
                ['Count per Level', 'Level 1: 20, Level 2: 15, Level 3: 10, Level 4: 5'],
                ['Question Type', $questionType],
                ['Dry Run', $dryRun ? 'Yes' : 'No']
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No questions will be saved to the database');
        }

        // Get all available topics
        $allTopics = $this->accountingTopicRepository->findAll();
        
        if (empty($allTopics)) {
            $io->error('No accounting topics found in the database. Please create topics first.');
            return Command::FAILURE;
        }

        $io->section('Found ' . count($allTopics) . ' topics to process');

        $levels = [
            'Level 1: Basics' => [20, ['categorise', 'tap-to-select', 'true-false']],
            'Level 2: Core Practice' => [15, ['tap-to-select','step-flow', 'multi-step']],
            'Level 3: Advanced' => [10, ['step-flow', 'multi-step']],
            'Level 4: Expert' => [5, ['multi-step']]
        ];

        $overallSummary = [];
        $totalRequested = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($allTopics as $accountingTopic) {
            $topicName = $accountingTopic->getSubTopic();
            $mainTopicName = $accountingTopic->getMainTopic();
            
            $io->section("Processing: $mainTopicName - $topicName");
            
            $topicSummary = [];
            foreach ($levels as $levelName => [$levelCount, $typePool]) {
                // Check if questions already exist for this topic and level
                $existingCount = $this->accountingQuestionRepository->countByTopicAndLevel($topicName, $levelName);
                
                if ($existingCount >= $levelCount) {
                    $io->text("Skipping $levelName - already has $existingCount questions (required: $levelCount)");
                    $topicSummary[] = [
                        'Level' => $levelName,
                        'Requested' => 0,
                        'Successful' => 0,
                        'Failed' => 0,
                        'Skipped' => $levelCount,
                        'Existing' => $existingCount
                    ];
                    $totalSkipped += $levelCount;
                    continue;
                }
                
                $questionsNeeded = $levelCount - $existingCount;
                $io->text("Generating for $levelName ($questionsNeeded questions needed, $existingCount already exist)");
                
                // Randomize question types for this level
                $questionTypes = [];
                for ($i = 0; $i < $questionsNeeded; $i++) {
                    $questionTypes[] = $typePool[array_rand($typePool)];
                }
                
                $result = $this->generatorService->generateMultipleQuestions(
                    $topicName,
                    $levelName,
                    $mainTopicName,
                    $questionsNeeded,
                    $questionTypes
                );
                
                $topicSummary[] = [
                    'Level' => $levelName,
                    'Requested' => $result['total_requested'],
                    'Successful' => $result['successful'],
                    'Failed' => $result['failed'],
                    'Skipped' => 0,
                    'Existing' => $existingCount
                ];
                
                $totalRequested += $result['total_requested'];
                $totalSuccessful += $result['successful'];
                $totalFailed += $result['failed'];
            }
            
            $io->table(['Level', 'Requested', 'Successful', 'Failed', 'Skipped', 'Existing'], $topicSummary);
            $overallSummary[] = [
                'Topic' => "$mainTopicName - $topicName",
                'Requested' => array_sum(array_column($topicSummary, 'Requested')),
                'Successful' => array_sum(array_column($topicSummary, 'Successful')),
                'Failed' => array_sum(array_column($topicSummary, 'Failed')),
                'Skipped' => array_sum(array_column($topicSummary, 'Skipped'))
            ];
        }

        $io->success('Batch generation completed for all topics!');
        $io->table(['Topic', 'Requested', 'Successful', 'Failed', 'Skipped'], $overallSummary);
        $io->table(['Total', 'Requested', 'Successful', 'Failed', 'Skipped'], [
            ['ALL TOPICS', $totalRequested, $totalSuccessful, $totalFailed, $totalSkipped]
        ]);
        
        return Command::SUCCESS;
    }

    private function generateForSpecificTopic(SymfonyStyle $io, ?string $topic, ?string $mainTopic, int $count, string $questionType, bool $dryRun): int
    {
        if (!$topic || !$mainTopic) {
            $io->error('Both topic and mainTopic arguments are required when not using --all-topics option.');
            return Command::FAILURE;
        }

        $io->title('AI Accounting Question Generator');
        $io->section('Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Topic', $topic],
                ['Levels', 'ALL LEVELS'],
                ['Count', 'Level 1: 20, Level 2: 15, Level 3: 10, Level 4: 5'],
                ['Question Type', $questionType],
                ['Main Topic', $mainTopic],
                ['Dry Run', $dryRun ? 'Yes' : 'No']
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No questions will be saved to the database');
        }

        $io->section('Generating Questions...');

        $levels = [
            'Level 1: Basics' => [20, ['categorise', 'tap-to-select', 'true-false']],
            'Level 2: Core Practice' => [15, ['tap-to-select','step-flow', 'multi-step']],
            'Level 3: Advanced' => [10, ['step-flow', 'multi-step']],
            'Level 4: Expert' => [5, ['multi-step']]
        ];
        $summary = [];
        
        foreach ($levels as $levelName => [$levelCount, $typePool]) {
            $io->section("Generating for $levelName ($levelCount questions)");
            
            // Check if questions already exist for this topic and level
            $existingCount = $this->accountingQuestionRepository->countByTopicAndLevel($topic, $levelName);
            
            if ($existingCount >= $levelCount) {
                $io->text("Skipping $levelName - already has $existingCount questions (required: $levelCount)");
                $summary[] = [
                    'Level' => $levelName,
                    'Requested' => 0,
                    'Successful' => 0,
                    'Failed' => 0,
                    'Skipped' => $levelCount,
                    'Existing' => $existingCount
                ];
                continue;
            }
            
            $questionsNeeded = $levelCount - $existingCount;
            $io->text("Generating $questionsNeeded questions for $levelName ($existingCount already exist)");
            
            // Randomize question types for this level
            $questionTypes = [];
            for ($i = 0; $i < $questionsNeeded; $i++) {
                $questionTypes[] = $typePool[array_rand($typePool)];
            }
            $result = $this->generatorService->generateMultipleQuestions(
                $topic,
                $levelName,
                $mainTopic,
                $questionsNeeded,
                $questionTypes
            );
            $summary[] = [
                'Level' => $levelName,
                'Requested' => $result['total_requested'],
                'Successful' => $result['successful'],
                'Failed' => $result['failed'],
                'Skipped' => 0,
                'Existing' => $existingCount
            ];
        }
        $io->success('Batch generation completed!');
        $io->table(['Level', 'Requested', 'Successful', 'Failed', 'Skipped', 'Existing'], $summary);
        return Command::SUCCESS;
    }
} 