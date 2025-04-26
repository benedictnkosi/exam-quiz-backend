<?php

namespace App\Command;

use App\Entity\Topic;
use App\Service\TextToSpeechService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:record-lecture',
    description: 'Record lectures for topics that have a lecture but no recording (processes 100 at a time)',
)]
class RecordLectureCommand extends Command
{
    private TextToSpeechService $textToSpeechService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        TextToSpeechService $textToSpeechService,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->textToSpeechService = $textToSpeechService;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $processedCount = 0;
        $successCount = 0;
        $failureCount = 0;

        try {
            // Find topics with a lecture but no recording (limit to 100)
            $topics = $this->entityManager->getRepository(Topic::class)
                ->createQueryBuilder('t')
                ->where('t.lecture IS NOT NULL')
                ->andWhere('t.recordingFileName IS NULL')
                ->setMaxResults(100)
                ->getQuery()
                ->getResult();

            if (empty($topics)) {
                $io->success('No topics found with lectures but no recordings!');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Found %d topics to process', count($topics)));

            foreach ($topics as $topic) {
                $processedCount++;
                $io->text(sprintf('Processing topic %d/%d:', $processedCount, count($topics)));
                $io->text('Main Topic: ' . $topic->getName());
                $io->text('Sub Topic: ' . $topic->getSubTopic());
                $io->text('Subject: ' . $topic->getSubject()->getName());

                // Generate shorter filename with timestamp
                $subjectName = strtolower(str_replace(' ', '_', $topic->getSubject()->getName()));
                $timestamp = date('YmdHis');
                $filename = sprintf('%s_%s', $subjectName, $timestamp);

                // Convert lecture to speech
                $filePath = $this->textToSpeechService->convertToSpeech($topic->getLecture(), $filename);

                if ($filePath) {
                    $topic->setRecordingFileName($filename . '.opus');
                    $this->entityManager->flush();
                    $successCount++;
                    $io->success('Recording generated successfully!');
                    $io->text('File saved at: ' . $filePath);
                } else {
                    $failureCount++;
                    $io->warning('Failed to generate recording for the topic.');
                }
            }

            $io->success(sprintf(
                'Processing complete! Successfully generated %d recordings, failed to generate %d recordings.',
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