<?php

namespace App\Service;

use App\Entity\LearnerCompletedChapter;
use App\Repository\LearnerCompletedChapterRepository;
use Doctrine\ORM\EntityManagerInterface;

class LearnerCompletedChapterService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LearnerCompletedChapterRepository $learnerCompletedChapterRepository
    ) {}

    public function addCompletedChapter(string $learnerUid, string $chapterName, string $bookTitle, ?int $duration = null, ?int $score = null): LearnerCompletedChapter
    {
        // Check if this chapter is already completed by this learner
        $existingCompletion = $this->learnerCompletedChapterRepository->findByLearnerUidAndChapterName($learnerUid, $chapterName);
        
        if ($existingCompletion) {
            // Update duration and score if provided
            if ($duration !== null) {
                $existingCompletion->setDuration($duration);
            }
            if ($score !== null) {
                $existingCompletion->setScore($score);
            }
            $this->entityManager->flush();
            return $existingCompletion;
        }

        $completedChapter = new LearnerCompletedChapter();
        $completedChapter->setLearnerUid($learnerUid);
        $completedChapter->setChapterName($chapterName);
        $completedChapter->setBookTitle($bookTitle);
        $completedChapter->setDuration($duration);
        $completedChapter->setScore($score);

        $this->entityManager->persist($completedChapter);
        $this->entityManager->flush();

        return $completedChapter;
    }

    public function getCompletedChaptersByLearner(string $learnerUid): array
    {
        return $this->learnerCompletedChapterRepository->findByLearnerUid($learnerUid);
    }

    public function getCompletedChaptersByLearnerAndBook(string $learnerUid, string $bookTitle): array
    {
        return $this->learnerCompletedChapterRepository->findByLearnerUidAndBookTitle($learnerUid, $bookTitle);
    }

    public function isChapterCompleted(string $learnerUid, string $chapterName): bool
    {
        $completedChapter = $this->learnerCompletedChapterRepository->findByLearnerUidAndChapterName($learnerUid, $chapterName);
        return $completedChapter !== null;
    }

    public function getCompletedChaptersCountByLearner(string $learnerUid): int
    {
        return $this->learnerCompletedChapterRepository->countByLearnerUid($learnerUid);
    }
} 