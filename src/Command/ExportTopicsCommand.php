<?php

namespace App\Command;

use App\Entity\Topic;
use App\Entity\Question;
use App\Entity\Subject;
use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-topics',
    description: 'Export topics to a JSON file with question counts and level mapping'
)]
class ExportTopicsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', 'topics_export.json')
            ->addOption('subject', 's', InputOption::VALUE_OPTIONAL, 'Filter by subject name')
            ->addOption('grade', 'g', InputOption::VALUE_OPTIONAL, 'Filter by grade number')
            ->addOption('active-only', null, InputOption::VALUE_NONE, 'Export only active questions (default)')
            ->setHelp('This command exports topics to a JSON file with question counts and level mapping. Example: app:export-topics --output=topics.json --subject="Mathematics"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $outputFile = $input->getOption('output');
        $subjectFilter = $input->getOption('subject');
        $gradeFilter = $input->getOption('grade');
        $activeOnly = $input->getOption('active-only');

        $io->title('Topics Export Tool');
        $io->section('Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Output File', $outputFile],
                ['Subject Filter', $subjectFilter ?: 'All'],
                ['Grade Filter', $gradeFilter ?: 'All'],
                ['Active Only', $activeOnly ? 'Yes' : 'Yes (default)']
            ]
        );

        $io->section('Fetching Topics and Questions...');

        // Build query to get topics with question counts
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t.name as mainTopic, t.subTopic, s.name as subjectName, g.number as gradeNumber, COUNT(q.id) as questionCount')
            ->from(Topic::class, 't')
            ->join('t.subject', 's')
            ->join('s.grade', 'g')
            ->leftJoin(Question::class, 'q', 'WITH', 'q.topic = t.subTopic AND q.subject = s')
            ->where('t.name IS NOT NULL')
            ->andWhere('t.subTopic IS NOT NULL')
            ->groupBy('t.name, t.subTopic, s.name, g.number')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('t.subTopic', 'ASC');

        // Apply filters
        if ($subjectFilter) {
            $qb->andWhere('s.name LIKE :subjectName')
               ->setParameter('subjectName', '%' . $subjectFilter . '%');
        }

        if ($gradeFilter) {
            $qb->andWhere('g.number = :gradeNumber')
               ->setParameter('gradeNumber', $gradeFilter);
        }

        // Always filter by active questions by default
        $qb->andWhere('q.active = :active OR q.active IS NULL')
           ->setParameter('active', true);

        $results = $qb->getQuery()->getResult();

        $io->info(sprintf('Found %d topic-subject combinations', count($results)));

        if (empty($results)) {
            $io->warning('No topics found matching the criteria');
            return Command::SUCCESS;
        }

        $io->section('Processing Topics...');

        // Group results by main topic only to avoid duplicates
        $groupedTopics = [];
        $progressBar = $io->createProgressBar(count($results));
        $progressBar->start();

        foreach ($results as $result) {
            $mainTopic = $result['mainTopic'];
            $subjectName = $result['subjectName'];
            $gradeNumber = $result['gradeNumber'];
            $subTopic = $result['subTopic'];
            $questionCount = (int) $result['questionCount'];

            // Skip if no questions
            if ($questionCount === 0) {
                $progressBar->advance();
                continue;
            }

            // Map grade number to level
            $level = $this->mapGradeToLevel($gradeNumber);

            // Use only main topic as key to avoid duplicates
            $key = $mainTopic;

            if (!isset($groupedTopics[$key])) {
                $groupedTopics[$key] = [
                    'mainTopic' => $mainTopic,
                    'subtopics' => [],
                    'questionCount' => 0,
                    'level' => $level,
                    'subjectName' => $subjectName
                ];
            }

            // Check if this subtopic already exists for this main topic
            $subtopicExists = false;
            foreach ($groupedTopics[$key]['subtopics'] as &$existingSubtopic) {
                if ($existingSubtopic['name'] === $subTopic) {
                    // If subtopic exists, add the question count
                    $existingSubtopic['questionCount'] += $questionCount;
                    $subtopicExists = true;
                    break;
                }
            }

            // If subtopic doesn't exist, add it
            if (!$subtopicExists) {
                $groupedTopics[$key]['subtopics'][] = [
                    'name' => $subTopic,
                    'questionCount' => $questionCount
                ];
            }

            // Add to total question count
            $groupedTopics[$key]['questionCount'] += $questionCount;

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Convert to array and sort
        $topics = array_values($groupedTopics);
        usort($topics, function ($a, $b) {
            // Sort by main topic name only since we're avoiding duplicates
            return strcmp($a['mainTopic'], $b['mainTopic']);
        });

        // Sort subtopics by name
        foreach ($topics as &$topic) {
            usort($topic['subtopics'], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        $exportData = ['topics' => $topics];

        $io->section('Writing to File...');

        // Write to JSON file
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($outputFile, $jsonContent) === false) {
            $io->error(sprintf('Failed to write to file: %s', $outputFile));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Successfully exported %d topics to %s (%s)',
            count($topics),
            $outputFile,
            $this->formatBytes(strlen($jsonContent))
        ));

        // Show summary
        $summary = [];
        $subjects = [];
        $levels = [];
        $totalQuestions = 0;

        foreach ($topics as $topic) {
            $subjects[$topic['subjectName']] = ($subjects[$topic['subjectName']] ?? 0) + 1;
            $levels[$topic['level']] = ($levels[$topic['level']] ?? 0) + 1;
            $totalQuestions += $topic['questionCount'];
        }

        $io->section('Export Summary');
        $io->table(
            ['Category', 'Count'],
            [
                ['Subjects', count($subjects)],
                ['Main Topics', count($topics)],
                ['Levels', count($levels)],
                ['Total Questions', $totalQuestions]
            ]
        );

        // Show breakdown by subject
        $io->section('Breakdown by Subject');
        $subjectBreakdown = [];
        foreach ($subjects as $subject => $count) {
            $subjectBreakdown[] = [$subject, $count];
        }
        $io->table(['Subject', 'Main Topics'], $subjectBreakdown);

        // Show breakdown by level
        $io->section('Breakdown by Level');
        $levelBreakdown = [];
        foreach ($levels as $level => $count) {
            $levelBreakdown[] = ["Level $level", $count];
        }
        $io->table(['Level', 'Main Topics'], $levelBreakdown);

        return Command::SUCCESS;
    }

    /**
     * Map grade number to level according to the specified mapping
     */
    private function mapGradeToLevel(int $gradeNumber): int
    {
        return match ($gradeNumber) {
            12 => 4,
            11 => 3,
            10 => 2,
            8, 9 => 1,
            default => 1 // Default to level 1 for any other grades
        };
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