<?php

namespace App\Command;

use App\Entity\Question;
use App\Service\StepGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-math-steps',
    description: 'Generate steps for mathematics questions that don\'t have them'
)]
class GenerateMathStepsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StepGenerationService $stepGenerationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating steps for mathematics questions');

        // Get questions that need steps
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('q')
            ->from(Question::class, 'q')
            ->join('q.subject', 's')
            ->where('s.name LIKE :subject')
            ->andWhere('q.steps IS NULL')
            ->andWhere('q.practice_status IS NULL')
            ->setParameter('subject', '%Mathematics%');

        $questions = $qb->getQuery()->getResult();
        $total = count($questions);

        if ($total === 0) {
            $io->success('No questions found that need steps generated.');
            return Command::SUCCESS;
        }

        $progress = $io->createProgressBar($total);
        $progress->start();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($questions as $question) {
            try {
                // Skip if required fields are missing
                if (!($question->getQuestion() && $question->getContext()) || !$question->getAnswer()) {
                    $io->warning(sprintf('question is %s', $question->getQuestion()));
                    $io->warning(sprintf('context is %s', $question->getContext()));
                    $io->warning(sprintf('answer is %s', $question->getAnswer()));
                    $io->warning(sprintf('Skipping question %d: Missing required fields', $question->getId()));
                    $skippedCount++;
                    $progress->advance();
                    continue;
                }

                $io->writeln("\nGenerating steps for question ID: " . $question->getId());
                $steps = $this->stepGenerationService->generateSteps($question, $output);

                if ($steps) {
                    $question->setSteps($steps);
                    $question->setPracticeStatus('pass');
                    $this->entityManager->persist($question);
                    $this->entityManager->flush(); // Flush after each question
                    $successCount++;
                    $io->writeln("\n[OK] Successfully generated steps for question " . $question->getId());
                } else {
                    $question->setPracticeStatus('fail');
                    $this->entityManager->persist($question);
                    $this->entityManager->flush();
                    $errorCount++;
                    $io->error(sprintf('Failed to generate steps for question %d', $question->getId()));
                }
            } catch (\Exception $e) {
                $errorCount++;
                $question->setPracticeStatus('fail');
                $this->entityManager->persist($question);
                $this->entityManager->flush();
                $io->error(sprintf('Error processing question %d: %s', $question->getId(), $e->getMessage()));
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $io->success(sprintf(
            'Processed %d questions: %d successful, %d errors, %d skipped',
            $total,
            $successCount,
            $errorCount,
            $skippedCount
        ));

        return Command::SUCCESS;
    }
}