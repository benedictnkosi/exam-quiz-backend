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
        $maxIterations = 100;

        try {
            for ($i = 0; $i < $maxIterations; $i++) {
                // Find one topic with a lecture but no recording
                $topic = $this->entityManager->getRepository(Topic::class)
                    ->createQueryBuilder('t')
                    ->where('t.lecture IS NOT NULL')
                    ->andWhere('t.recordingFileName IS NULL')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$topic) {
                    $io->success('No more topics found with lectures but no recordings!');
                    break;
                }

                $processedCount++;
                $io->text(sprintf('Processing topic %d/%d:', $processedCount, $maxIterations));
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
                    // Find all topics with the same name and subtopic
                    $matchingTopics = $this->entityManager->getRepository(Topic::class)
                        ->createQueryBuilder('t')
                        ->where('t.name = :name')
                        ->andWhere('t.subTopic = :subTopic')
                        ->setParameter('name', $topic->getName())
                        ->setParameter('subTopic', $topic->getSubTopic())
                        ->getQuery()
                        ->getResult();

                    // Update all matching topics with the recording filename
                    foreach ($matchingTopics as $matchingTopic) {
                        $matchingTopic->setRecordingFileName($filename . '.opus');
                    }

                    $this->entityManager->flush();
                    $successCount++;
                    $io->success('Recording generated successfully!');
                    $io->text('File saved at: ' . $filePath);
                    $io->text(sprintf('Updated %d matching topics with the same name and subtopic', count($matchingTopics)));
                } else {
                    $failureCount++;
                    $io->warning('Failed to generate recording for the topic.');
                }

                // Clear the entity manager to free memory
                $this->entityManager->clear();
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