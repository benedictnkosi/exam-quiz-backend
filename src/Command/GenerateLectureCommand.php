<?php

namespace App\Command;

use App\Entity\Topic;
use App\Service\TopicPopulationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-lecture',
    description: 'Generate lectures for topics that have no lecture (processes 100 at a time)',
)]
class GenerateLectureCommand extends Command
{
    private TopicPopulationService $topicPopulationService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        TopicPopulationService $topicPopulationService,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->topicPopulationService = $topicPopulationService;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $processedCount = 0;
        $successCount = 0;
        $failureCount = 0;

        try {
            // Find topics with null lecture (limit to 100)
            $topics = $this->entityManager->getRepository(Topic::class)
                ->createQueryBuilder('t')
                ->join('t.subject', 's')
                ->where('t.lecture IS NULL')
                ->andWhere('s.name NOT LIKE :mathematics')
                ->setParameter('mathematics', '%Mathematics%')
                ->setMaxResults(500)
                ->getQuery()
                ->getResult();

            if (empty($topics)) {
                $io->success('No topics found with null lectures!');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Found %d topics to process', count($topics)));

            foreach ($topics as $topic) {
                $processedCount++;
                $io->text(sprintf('Processing topic %d/%d:', $processedCount, count($topics)));
                $io->text('Main Topic: ' . $topic->getName());
                $io->text('Sub Topic: ' . $topic->getSubTopic());
                $io->text('Subject: ' . $topic->getSubject()->getName());

                // Update the lecture for this topic
                $success = $this->topicPopulationService->updateTopicLecture($topic);

                if ($success) {
                    $successCount++;
                    $io->success('Lecture generated successfully!');
                } else {
                    $failureCount++;
                    $io->warning('Failed to generate lecture for the topic.');
                }

                // Flush after each topic to ensure progress is saved
                $this->entityManager->flush();
            }

            $io->success(sprintf(
                'Processing complete! Successfully generated %d lectures, failed to generate %d lectures.',
                $successCount,
                $failureCount
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}