<?php

namespace App\Command;

use App\Entity\Subject;
use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-exam-dates',
    description: 'Updates exam dates for Grade 12 subjects from CSV file',
)]
class UpdateExamDatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Get Grade 12 entity
        $grade = $this->entityManager->getRepository(Grade::class)->findOneBy(['number' => 12]);
        if (!$grade) {
            $io->error('Grade 12 not found in database');
            return Command::FAILURE;
        }

        // Read and process CSV file
        $csvFile = 'exam-timetable.csv';
        if (!file_exists($csvFile)) {
            $io->error('CSV file not found');
            return Command::FAILURE;
        }

        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            $io->error('Could not open CSV file');
            return Command::FAILURE;
        }

        $updatedCount = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $subjectName = trim($data[0]);
            $examDate = \DateTime::createFromFormat('Y-m-d H:i:s', trim($data[1]));

            if (!$examDate) {
                $io->warning("Invalid date format for subject: {$subjectName}");
                continue;
            }

            // Find the subject
            $subject = $this->entityManager->getRepository(Subject::class)
                ->findOneBy([
                    'name' => $subjectName,
                    'grade' => $grade
                ]);

            if ($subject) {
                $subject->setExamDate($examDate);
                $updatedCount++;
            } else {
                $io->warning("Subject not found: {$subjectName}");
            }
        }

        fclose($handle);

        // Save all changes
        $this->entityManager->flush();

        $io->success("Successfully updated exam dates for {$updatedCount} subjects");

        return Command::SUCCESS;
    }
} 