<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerNote;
use App\Repository\LearnerNoteRepository;
use App\Repository\LearnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerNoteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LearnerNoteRepository $noteRepository,
        private LearnerRepository $learnerRepository,
        private LoggerInterface $logger
    ) {
    }

    public function createNote(string $uid, string $text, string $subjectName): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $learner = $this->learnerRepository->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return [
                'status' => 'NOK',
                'message' => 'Learner not found'
            ];
        }

        $note = new LearnerNote();
        $note->setText($text);
        $note->setSubjectName($subjectName);
        $note->setLearner($learner);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return [
            'status' => 'OK',
            'message' => 'Note created successfully',
            'note' => $note
        ];
    }

    public function getNotesByLearnerAndSubject(string $uid, string $subjectName): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $notes = $this->noteRepository->findByLearnerAndSubject($uid, $subjectName);

        return [
            'status' => 'OK',
            'notes' => $notes
        ];
    }

    public function deleteNote(string $uid, int $noteId): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $note = $this->noteRepository->findOneByLearnerAndId($uid, $noteId);
        if (!$note) {
            return [
                'status' => 'NOK',
                'message' => 'Note not found or does not belong to the learner'
            ];
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return [
            'status' => 'OK',
            'message' => 'Note deleted successfully'
        ];
    }
} 