<?php

namespace App\Command;

use App\Entity\Learner;
use App\Entity\Book;
use App\Entity\LearnerReading;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-dummy-readings',
    description: 'Generates dummy learner reading records for a specific learner',
)]
class GenerateDummyLearnerReadingsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find the learner
        $learner = $this->entityManager->getRepository(Learner::class)
            ->findOneBy(['uid' => 'u65pX1a9KCbshI5VuprMcgVVfQl2']);

        if (!$learner) {
            $io->error('Learner not found');
            return Command::FAILURE;
        }

        // Get all books
        $books = $this->entityManager->getRepository(Book::class)->findAll();

        if (empty($books)) {
            $io->error('No books found in the database');
            return Command::FAILURE;
        }

        // Generate reading records for each book
        foreach ($books as $book) {
            // Create a reading record
            $reading = new LearnerReading();
            $reading->setLearner($learner);
            $reading->setChapter($book);

            // Randomly assign a status
            $statuses = ['completed', 'in_progress', 'not_started'];
            $reading->setStatus($statuses[array_rand($statuses)]);

            // Set a random date within the last 30 days
            $date = new \DateTimeImmutable();
            $date = $date->modify('-' . rand(0, 30) . ' days');
            $reading->setDate($date);

            $this->entityManager->persist($reading);
        }

        $this->entityManager->flush();

        $io->success('Successfully generated reading records for ' . count($books) . ' books');

        return Command::SUCCESS;
    }
}