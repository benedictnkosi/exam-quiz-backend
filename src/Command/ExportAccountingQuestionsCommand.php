<?php

namespace App\Command;

use App\Repository\AccountingQuestionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-accounting-questions',
    description: 'Export all accounting questions to a JSON file'
)]
class ExportAccountingQuestionsCommand extends Command
{
    public function __construct(
        private AccountingQuestionRepository $questionRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', 'accounting_questions_export.json')
            ->addOption('main-topic', null, InputOption::VALUE_OPTIONAL, 'Filter by main topic')
            ->addOption('sub-topic', null, InputOption::VALUE_OPTIONAL, 'Filter by sub topic')
            ->addOption('level', null, InputOption::VALUE_OPTIONAL, 'Filter by level')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Filter by question type')
            ->addOption('active-only', null, InputOption::VALUE_NONE, 'Export only active questions (default)')
            ->setHelp('This command exports all accounting questions to a JSON file. Example: app:export-accounting-questions --output=questions.json --main-topic="Financial Statements"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $outputFile = $input->getOption('output');
        $mainTopic = $input->getOption('main-topic');
        $subTopic = $input->getOption('sub-topic');
        $level = $input->getOption('level');
        $questionType = $input->getOption('type');
        $activeOnly = $input->getOption('active-only');

        $io->title('Accounting Questions Export Tool');
        $io->section('Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Output File', $outputFile],
                ['Main Topic Filter', $mainTopic ?: 'All'],
                ['Sub Topic Filter', $subTopic ?: 'All'],
                ['Level Filter', $level ?: 'All'],
                ['Question Type Filter', $questionType ?: 'All'],
                ['Active Only', $activeOnly ? 'Yes' : 'Yes (default)']
            ]
        );

        $io->section('Fetching Questions...');

        // Build query based on filters
        $qb = $this->questionRepository->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->orderBy('at.mainTopic', 'ASC')
            ->addOrderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.level', 'ASC')
            ->addOrderBy('aq.questionId', 'ASC');

        // Apply filters
        if ($mainTopic) {
            $qb->andWhere('at.mainTopic = :mainTopic')
               ->setParameter('mainTopic', $mainTopic);
        }

        if ($subTopic) {
            $qb->andWhere('at.subTopic = :subTopic')
               ->setParameter('subTopic', $subTopic);
        }

        if ($level) {
            $qb->andWhere('aq.level = :level')
               ->setParameter('level', $level);
        }

        if ($questionType) {
            $qb->andWhere('aq.questionType = :questionType')
               ->setParameter('questionType', $questionType);
        }

        // Always filter by active questions by default
        $qb->andWhere('aq.active = :active')
           ->setParameter('active', true);

        $questions = $qb->getQuery()->getResult();

        $io->info(sprintf('Found %d questions to export', count($questions)));

        if (empty($questions)) {
            $io->warning('No questions found matching the criteria');
            return Command::SUCCESS;
        }

        $io->section('Processing Questions...');

        $exportData = [];
        $progressBar = $io->createProgressBar(count($questions));
        $progressBar->start();

        foreach ($questions as $question) {
            $accountingTopic = $question->getAccountingTopic();
            
            $questionData = [
                'mainTopic' => $accountingTopic->getMainTopic(),
                'subTopic' => $accountingTopic->getSubTopic(),
                'level' => $question->getLevel(),
                'question_id' => $question->getQuestionId(),
                'question_type' => $question->getQuestionType(),
                'prompt' => $question->getPrompt(),
                'active' => $question->isActive() ? 1 : 0
            ];

            // Include all fields that have values, regardless of question type
            if ($question->getOptions() !== null) {
                $questionData['options'] = json_encode($question->getOptions());
            }
            
            if ($question->getAnswer() !== null) {
                $questionData['answer'] = $question->getAnswer();
            }
            
            if ($question->getCategories() !== null) {
                $questionData['categories'] = json_encode($question->getCategories());
            }
            
            if ($question->getItems() !== null) {
                $questionData['items'] = json_encode($question->getItems());
            }
            
            if ($question->getCorrectOrder() !== null) {
                $questionData['correctOrder'] = json_encode($question->getCorrectOrder());
            }
            
            if ($question->getPairs() !== null) {
                $questionData['pairs'] = json_encode($question->getPairs());
            }
            
            if ($question->getContext() !== null) {
                $questionData['context'] = $question->getContext();
            }
            
            if ($question->getSteps() !== null) {
                $questionData['steps'] = json_encode($question->getSteps());
            }
            
            if ($question->getExplanation() !== null) {
                $questionData['explanation'] = $question->getExplanation();
            }

            $exportData[] = $questionData;
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->section('Writing to File...');

        // Write to JSON file
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($outputFile, $jsonContent) === false) {
            $io->error(sprintf('Failed to write to file: %s', $outputFile));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Successfully exported %d questions to %s (%s)',
            count($exportData),
            $outputFile,
            $this->formatBytes(strlen($jsonContent))
        ));

        // Show summary
        $summary = [];
        $mainTopics = [];
        $subTopics = [];
        $levels = [];
        $types = [];

        foreach ($exportData as $question) {
            $mainTopics[$question['mainTopic']] = ($mainTopics[$question['mainTopic']] ?? 0) + 1;
            $subTopics[$question['subTopic']] = ($subTopics[$question['subTopic']] ?? 0) + 1;
            $levels[$question['level']] = ($levels[$question['level']] ?? 0) + 1;
            $types[$question['question_type']] = ($types[$question['question_type']] ?? 0) + 1;
        }

        $io->section('Export Summary');
        $io->table(
            ['Category', 'Count'],
            [
                ['Main Topics', count($mainTopics)],
                ['Sub Topics', count($subTopics)],
                ['Levels', count($levels)],
                ['Question Types', count($types)],
                ['Total Questions', count($exportData)]
            ]
        );

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
} 