<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Book;
use App\Entity\LearnerReading;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Service\LearnerPromotionService;

class LearnerReadingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private LearnerPromotionService $learnerPromotionService
    ) {
    }

    private function calculateLearnerStreak(Learner $learner): int
    {
        $today = new \DateTimeImmutable();
        $yesterday = $today->modify('-1 day');

        // Get all completed readings ordered by date
        $readings = $this->entityManager->getRepository(LearnerReading::class)
            ->createQueryBuilder('lr')
            ->where('lr.learner = :learner')
            ->andWhere('lr.status = :status')
            ->setParameter('learner', $learner)
            ->setParameter('status', 'completed')
            ->orderBy('lr.date', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($readings)) {
            return 0;
        }

        $streak = 0;
        $currentDate = $today;

        foreach ($readings as $reading) {
            $readingDate = $reading->getDate();

            // If the reading is from today or yesterday, count it
            if (
                $readingDate->format('Y-m-d') === $currentDate->format('Y-m-d') ||
                $readingDate->format('Y-m-d') === $yesterday->format('Y-m-d')
            ) {
                $streak++;
                $currentDate = $readingDate;
            } else {
                // If there's a gap in dates, break the streak
                break;
            }
        }

        return $streak;
    }

    private function calculateReadingStats(Learner $learner): array
    {
        // Get all completed readings ordered by date
        $readings = $this->entityManager->getRepository(LearnerReading::class)
            ->createQueryBuilder('lr')
            ->where('lr.learner = :learner')
            ->andWhere('lr.status = :status')
            ->setParameter('learner', $learner)
            ->setParameter('status', 'completed')
            ->orderBy('lr.date', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($readings)) {
            return [
                'completedChapters' => 0,
                'readingDays' => 0,
                'speeds' => []
            ];
        }

        // Count unique reading days and collect speed data
        $readingDays = [];
        $speeds = [];

        foreach ($readings as $reading) {
            $date = $reading->getDate()->format('Y-m-d');
            $readingDays[$date] = true;

            // Collect speed data with date and score
            if ($reading->getSpeed() > 0) {
                $speeds[] = [
                    'date' => $date,
                    'speed' => $reading->getSpeed(),
                    'score' => $reading->getScore(),
                    'chapterNumber' => $reading->getChapter()->getChapterNumber()
                ];
            }
        }

        return [
            'completedChapters' => count($readings),
            'readingDays' => count($readingDays),
            'speeds' => $speeds
        ];
    }

    public function getNextChapter(string $learnerUid): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Get learner's reading level
            $readingLevel = $learner->getReadingLevel();

            // First, check for any in-progress chapters at the learner's level
            $inProgressChapter = $this->entityManager->getRepository(LearnerReading::class)
                ->createQueryBuilder('lr')
                ->join('lr.chapter', 'b')
                ->where('lr.learner = :learner')
                ->andWhere('lr.status = :status')
                ->andWhere('b.level = :level')
                ->setParameter('learner', $learner)
                ->setParameter('status', 'in_progress')
                ->setParameter('level', $readingLevel)
                ->orderBy('lr.date', 'DESC')
                ->addOrderBy('lr.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($inProgressChapter) {
                $book = $inProgressChapter->getChapter();
                $today = new \DateTimeImmutable();
                $isPublished = $book->getPublishDate() <= $today;

                return [
                    'status' => 'OK',
                    'chapter' => [
                        'id' => $book->getId(),
                        'chapterName' => $book->getChapterName(),
                        'summary' => $book->getSummary(),
                        'content' => $isPublished ? $book->getContent() : null,
                        'level' => $book->getLevel(),
                        'chapterNumber' => $book->getChapterNumber(),
                        'status' => 'in_progress',
                        'publishDate' => $book->getPublishDate()?->format('Y-m-d H:i:s'),
                        'image' => $book->getImage()
                    ]
                ];
            }

            // Get the last completed chapter number for this level
            $lastCompletedChapter = $this->entityManager->getRepository(LearnerReading::class)
                ->createQueryBuilder('lr')
                ->join('lr.chapter', 'b')
                ->where('lr.learner = :learner')
                ->andWhere('lr.status = :status')
                ->setParameter('learner', $learner)
                ->setParameter('status', 'completed')
                ->orderBy('b.chapterNumber', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $nextChapterNumber = 1;
            if ($lastCompletedChapter) {
                $nextChapterNumber = $lastCompletedChapter->getChapter()->getChapterNumber() + 1;
            }

            // Log the next chapter number, learner UID, and level
            $this->logger->info('Next chapter number calculated', [
                'learnerUid' => $learnerUid,
                'level' => $readingLevel,
                'nextChapterNumber' => $nextChapterNumber
            ]);

            // Get the next chapter at the learner's level
            $nextBook = $this->entityManager->getRepository(Book::class)
                ->createQueryBuilder('b')
                ->where('b.level = :level')
                ->andWhere('b.status = :active')
                ->andWhere('b.chapterNumber = :chapterNumber')
                ->setParameter('level', $readingLevel)
                ->setParameter('active', 'active')
                ->setParameter('chapterNumber', $nextChapterNumber)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$nextBook) {
                return [
                    'status' => 'OK',
                    'message' => 'No more chapters available at current level',
                    'chapter' => null
                ];
            }

            // Check if the chapter is published
            $today = new \DateTimeImmutable();
            $isPublished = $nextBook->getPublishDate() <= $today;

            // Create a new reading record for the book
            $reading = new LearnerReading();
            $reading->setLearner($learner);
            $reading->setChapter($nextBook);
            $reading->setStatus('in_progress');
            $reading->setDate(new \DateTimeImmutable());

            $this->entityManager->persist($reading);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'chapter' => [
                    'id' => $nextBook->getId(),
                    'chapterName' => $nextBook->getChapterName(),
                    'summary' => $nextBook->getSummary(),
                    'content' => $isPublished ? $nextBook->getContent() : null,
                    'level' => $nextBook->getLevel(),
                    'chapterNumber' => $nextBook->getChapterNumber(),
                    'status' => 'in_progress',
                    'publishDate' => $nextBook->getPublishDate()?->format('Y-m-d H:i:s'),
                    'image' => $nextBook->getImage()
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting next chapter: ' . $e->getMessage()
            ];
        }
    }

    public function markChapterComplete(string $learnerUid, int $chapterId, int $duration = 0, int $score = 0): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Find the reading record
            $reading = $this->entityManager->getRepository(LearnerReading::class)
                ->createQueryBuilder('lr')
                ->join('lr.chapter', 'b')
                ->where('lr.learner = :learner')
                ->andWhere('b.id = :chapterId')
                ->setParameter('learner', $learner)
                ->setParameter('chapterId', $chapterId)
                ->orderBy('lr.date', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$reading) {
                return [
                    'status' => 'NOK',
                    'message' => 'Reading record not found'
                ];
            }

            // Get the chapter to calculate reading speed
            $chapter = $reading->getChapter();
            $wordCount = $chapter->getWordCount();

            // Calculate reading speed (words per minute)
            $speed = 0;
            if ($duration > 0) {
                // Convert duration from seconds to minutes for WPM calculation
                $speed = (int) round(($wordCount / ($duration / 60)));
            }

            // Update the status to completed and set duration, score, and speed
            if ($score < 75) {
                $reading->setStatus('fail');
            } else {
                $reading->setStatus('completed');
                // Add points to learner's total points when chapter is completed
                $currentPoints = $learner->getPoints() ?? 0;
                $learner->setPoints($currentPoints + 1);
                $this->entityManager->persist($learner);
            }
            $reading->setDuration($duration);
            $reading->setScore($score);
            $reading->setSpeed($speed);
            $this->entityManager->persist($reading);
            $this->entityManager->flush();

            // Check for promotion after completing the chapter
            $promotionResult = $this->learnerPromotionService->checkAndPromoteLearner($learnerUid);

            return [
                'status' => 'OK',
                'message' => 'Chapter marked as complete',
                'data' => [
                    'duration' => $duration,
                    'score' => $score,
                    'speed' => $speed,
                    'promotion' => $promotionResult,
                    'points' => $learner->getPoints()
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error marking chapter as complete: ' . $e->getMessage()
            ];
        }
    }

    public function getPastChapters(string $learnerUid): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $learnerLevel = $learner->getReadingLevel();

            // Get all completed chapters regardless of level
            $pastChapters = $this->entityManager->getRepository(Book::class)
                ->createQueryBuilder('b')
                ->join('App\Entity\LearnerReading', 'lr', 'WITH', 'lr.chapter = b')
                ->where('lr.learner = :learner')
                ->andWhere('lr.status = :status')
                ->setParameter('learner', $learner)
                ->setParameter('status', 'completed')
                ->orderBy('b.chapterNumber', 'ASC')
                ->getQuery()
                ->getResult();

            $chapters = [];
            foreach ($pastChapters as $chapter) {
                // get the chapter, as for the current learner reading level using the chapter number
                $chapter = $this->entityManager->getRepository(Book::class)
                    ->findOneBy(['chapterNumber' => $chapter->getChapterNumber(), 'level' => $learnerLevel]);

                $chapters[] = [
                    'id' => $chapter->getId(),
                    'chapterName' => $chapter->getChapterName(),
                    'summary' => $chapter->getSummary(),
                    'level' => $chapter->getLevel(),
                    'chapterNumber' => $chapter->getChapterNumber(),
                    'publishDate' => $chapter->getPublishDate()?->format('Y-m-d H:i:s'),
                    'readingDuration' => $chapter->getReadingDuration(),
                    'wordCount' => $chapter->getWordCount(),
                    'image' => $chapter->getImage()
                ];
            }

            return [
                'status' => 'OK',
                'chapters' => $chapters
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting past chapters: ' . $e->getMessage()
            ];
        }
    }

    public function getChapterById(string $learnerUid, int $chapterId): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $today = new \DateTimeImmutable();

            // Get the chapter and check if it's published
            $chapter = $this->entityManager->getRepository(Book::class)
                ->createQueryBuilder('b')
                ->where('b.id = :id')
                ->andWhere('b.publishDate <= :today')
                ->andWhere('b.status = :status')
                ->setParameter('id', $chapterId)
                ->setParameter('today', $today)
                ->setParameter('status', 'active')
                ->getQuery()
                ->getOneOrNullResult();

            if (!$chapter) {
                return [
                    'status' => 'NOK',
                    'message' => 'Chapter not found or not yet published'
                ];
            }

            return [
                'status' => 'OK',
                'chapter' => [
                    'id' => $chapter->getId(),
                    'chapterName' => $chapter->getChapterName(),
                    'summary' => $chapter->getSummary(),
                    'content' => $chapter->getContent(),
                    'level' => $chapter->getLevel(),
                    'chapterNumber' => $chapter->getChapterNumber(),
                    'status' => 'in_progress',
                    'publishDate' => $chapter->getPublishDate()?->format('Y-m-d H:i:s'),
                    'image' => $chapter->getImage()
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting chapter: ' . $e->getMessage()
            ];
        }
    }

    public function getLearnerReadingStats(string $learnerUid): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Calculate learner's streak and reading stats
            $streak = $this->calculateLearnerStreak($learner);
            $stats = $this->calculateReadingStats($learner);

            return [
                'status' => 'OK',
                'streak' => $streak,
                'points' => $learner->getPoints() ?? 0,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting learner reading stats: ' . $e->getMessage()
            ];
        }
    }
}