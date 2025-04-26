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
    description: 'Generate lecture for the first topic that has no lecture',
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

        try {
            // Find the first topic with null lecture
            $topic = $this->entityManager->getRepository(Topic::class)
                ->createQueryBuilder('t')
                ->where('t.lecture IS NULL')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$topic) {
                $io->success('No topics found with null lectures!');
                return Command::SUCCESS;
            }

            $io->info('Found topic to process:');
            $io->text('Main Topic: ' . $topic->getName());
            $io->text('Sub Topic: ' . $topic->getSubTopic());
            $io->text('Subject: ' . $topic->getSubject()->getName());

            // Update the lecture for this topic
            $success = $this->topicPopulationService->updateTopicLecture($topic);

            if ($success) {
                $io->success('Lecture generated successfully!');
            } else {
                $io->warning('Failed to generate lecture for the topic.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}