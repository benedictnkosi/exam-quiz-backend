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
    description: 'Record lecture for the first topic that has a lecture but no recording',
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

        try {
            // Find the first topic with a lecture but no recording
            $topic = $this->entityManager->getRepository(Topic::class)
                ->createQueryBuilder('t')
                ->where('t.lecture IS NOT NULL')
                ->andWhere('t.recordingFileName IS NULL')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$topic) {
                $io->success('No topics found with lectures but no recordings!');
                return Command::SUCCESS;
            }

            $io->info('Found topic to process:');
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
                $io->success('Recording generated successfully!');
                $io->text('File saved at: ' . $filePath);
            } else {
                $io->warning('Failed to generate recording for the topic.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}