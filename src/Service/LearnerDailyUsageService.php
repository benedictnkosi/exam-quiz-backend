<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerDailyUsage;
use App\Entity\LearnerPodcastRequest;
use App\Repository\LearnerDailyUsageRepository;
use App\Repository\LearnerPodcastRequestRepository;
use App\Repository\LearnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Repository\ResultRepository;

class LearnerDailyUsageService
{
    private const TIMEZONE = 'Africa/Johannesburg';

    private $DAILY_QUIZ_LIMIT = 10;
    private $SILVER_DAILY_QUIZ_LIMIT = 999;
    private $BRONZE_DAILY_QUIZ_LIMIT = 999;
    private $GOLD_DAILY_QUIZ_LIMIT = 999;
    private $DAILY_LESSON_LIMIT = 10;
    private $DAILY_PODCAST_LIMIT = 1;
    private $DAILY_MATHS_PRACTICE_LIMIT = 5;
    private $LIFETIME_MATHS_PRACTICE_LIMIT = 15;

    private $LIFETIME_QUIZ_PRACTICE_LIMIT = 70;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LearnerRepository $learnerRepository,
        private readonly LearnerDailyUsageRepository $usageRepository,
        private readonly LearnerPodcastRequestRepository $podcastRequestRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDailyUsageByLearnerUid(string $learnerUid): array
    {
        $this->logger->info("Starting Method: " . __METHOD__ . " with learnerUid: {$learnerUid}");

        try {
            // Find the learner
            $this->logger->debug("Searching for learner with UID: {$learnerUid}");
            $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                $this->logger->warning("Learner not found with UID: {$learnerUid}");
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }
            $this->logger->debug("Learner found with ID: {$learner->getId()}, subscription: {$learner->getSubscription()}");

            // Get today's date with timezone
            $timezone = new \DateTimeZone(self::TIMEZONE);
            $today = new \DateTimeImmutable('now', $timezone);
            $this->logger->debug("Today's date in timezone " . self::TIMEZONE . ": " . $today->format('Y-m-d H:i:s'));

            // Get or create today's usage record
            $this->logger->debug("Looking for existing usage record for learner {$learner->getId()} on date {$today->format('Y-m-d')}");
            $usage = $this->usageRepository->findByLearnerAndDate($learner->getId(), $today);
            if (!$usage) {
                $this->logger->debug("No existing usage record found, creating new one");
                $usage = new LearnerDailyUsage();
                $usage->setLearner($learner);
                $usage->setDate($today);
                $this->entityManager->persist($usage);
                $this->entityManager->flush();
                $this->logger->debug("New usage record created and persisted");
            } else {
                $this->logger->debug("Existing usage record found - Quiz: {$usage->getQuiz()}, Lesson: {$usage->getLesson()}, MathsPractice: {$usage->getMathsPractice()}");
            }

            // Get podcast usage from podcast requests
            $this->logger->debug("Counting daily podcast requests for learner {$learner->getId()}");
            $dailyPodcastRequests = $this->podcastRequestRepository->countDailyRequests($learner->getId(), $today);
            $this->logger->debug("Daily podcast requests count: {$dailyPodcastRequests}");

            $subscription = $learner->getSubscription();
            $this->logger->debug("Processing subscription type: {$subscription}");

            $remainingQuiz = 0;
            $remainingLesson = 0;
            $remainingPodcast = 0;
            $remainingMathsPractice = 0;

            if (str_contains($subscription, 'free')) {
                $this->logger->debug("Free subscription detected - calculating remaining limits");
                $remainingQuiz = $this->DAILY_QUIZ_LIMIT - $usage->getQuiz();
                $remainingLesson = $this->DAILY_LESSON_LIMIT - $usage->getLesson();
                $remainingPodcast = $this->DAILY_PODCAST_LIMIT - $dailyPodcastRequests;
                $remainingMathsPractice = $this->DAILY_MATHS_PRACTICE_LIMIT - $usage->getMathsPractice();

                $this->logger->debug("Free subscription limits - Quiz: {$this->DAILY_QUIZ_LIMIT} - {$usage->getQuiz()} = {$remainingQuiz}");
                $this->logger->debug("Free subscription limits - Lesson: {$this->DAILY_LESSON_LIMIT} - {$usage->getLesson()} = {$remainingLesson}");
                $this->logger->debug("Free subscription limits - Podcast: {$this->DAILY_PODCAST_LIMIT} - {$dailyPodcastRequests} = {$remainingPodcast}");
                $this->logger->debug("Free subscription limits - MathsPractice: {$this->DAILY_MATHS_PRACTICE_LIMIT} - {$usage->getMathsPractice()} = {$remainingMathsPractice}");
            } else {
                $this->logger->debug("GOLD subscription detected - setting unlimited limits");
                $remainingQuiz = 999;
                $remainingLesson = 999;
                $remainingPodcast = 999;
                $remainingMathsPractice = 999;
            }

            $result = [
                'status' => 'OK',
                'data' => [
                    'quiz' => $remainingQuiz,
                    'lesson' => $remainingLesson,
                    'podcast' => $remainingPodcast,
                    'maths_practice' => $remainingMathsPractice,
                    'date' => $usage->getDate()->format('Y-m-d')
                ]
            ];

            // Add lifetime quiz remaining info
            $lifetimeQuiz = $this->getLifetimeQuizRemaining($learnerUid);
            if ($lifetimeQuiz['status'] === 'OK') {
                $result['data']['lifetime_quiz_limit'] = $lifetimeQuiz['lifetime_quiz_limit'];
                $result['data']['quizzes_taken'] = $lifetimeQuiz['quizzes_taken'];
                $result['data']['quizzes_remaining'] = $lifetimeQuiz['quizzes_remaining'];
            }else{
                $result['data']['lifetime_quiz_limit'] = 0;
            }

            $this->logger->debug("Returning result: " . json_encode($result));
            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Exception in " . __METHOD__ . ": " . $e->getMessage(), [
                'learnerUid' => $learnerUid,
                'exception' => $e
            ]);
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving daily usage data'
            ];
        }
    }

    public function getDailyUsageByDateRange(string $learnerUid, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        try {
            // Find the learner
            $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Ensure dates are in the correct timezone
            $timezone = new \DateTimeZone(self::TIMEZONE);
            $startDate = $startDate->setTimezone($timezone);
            $endDate = $endDate->setTimezone($timezone);

            // Get usage records for date range
            $usageRecords = $this->usageRepository->findByLearnerAndDateRange(
                $learner->getId(),
                $startDate,
                $endDate
            );

            $data = array_map(function (LearnerDailyUsage $usage) {
                return [
                    'quiz' => $usage->getQuiz(),
                    'lesson' => $usage->getLesson(),
                    'podcast' => $usage->getPodcast(),
                    'date' => $usage->getDate()->format('Y-m-d')
                ];
            }, $usageRecords);

            return [
                'status' => 'OK',
                'data' => $data
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving daily usage data'
            ];
        }
    }

    public function incrementQuizUsage(Learner $learner): void
    {
        $this->logger->info("Incrementing quiz usage for learner {$learner->getId()}");
        $usage = $this->getOrCreateDailyUsage($learner);
        $usage->incrementQuiz();
        $this->entityManager->flush();
    }

    public function incrementLessonUsage(Learner $learner): void
    {
        $this->logger->info("Incrementing lesson usage for learner {$learner->getId()}");
        $usage = $this->getOrCreateDailyUsage($learner);
        $usage->incrementLesson();
        $this->entityManager->flush();
    }

    public function incrementPodcastUsage(Learner $learner, string $podcastFileId): void
    {
        $this->logger->info("Incrementing podcast usage for learner {$learner->getId()} with file {$podcastFileId}");

        try {
            $timezone = new \DateTimeZone(self::TIMEZONE);
            $today = new \DateTimeImmutable('today', $timezone);

            // Check if a request for this podcast file already exists today
            $existingRequest = $this->podcastRequestRepository->findOneBy([
                'learner' => $learner,
                'podcastFileId' => $podcastFileId,
                'requestedAt' => $today
            ]);

            if ($existingRequest) {
                $this->logger->info("Podcast request already exists for today, skipping creation");
                return;
            }

            // Create new podcast request record
            $podcastRequest = new LearnerPodcastRequest();
            $podcastRequest->setLearner($learner);
            $podcastRequest->setPodcastFileId($podcastFileId);
            $podcastRequest->setRequestedAt(new \DateTimeImmutable('now', $timezone));

            $this->entityManager->persist($podcastRequest);
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Log the duplicate entry attempt
            $this->logger->info("Duplicate podcast request detected and prevented", [
                'learner_id' => $learner->getId(),
                'podcast_file_id' => $podcastFileId
            ]);
            // No need to rethrow as this is an expected case
        } catch (\Exception $e) {
            // Log unexpected errors
            $this->logger->error("Error incrementing podcast usage", [
                'error' => $e->getMessage(),
                'learner_id' => $learner->getId(),
                'podcast_file_id' => $podcastFileId
            ]);
            throw $e; // Re-throw unexpected errors
        }
    }

    public function incrementMathsPracticeUsage(Learner $learner, int $questionId, bool $isCorrect, int $correctSteps = 0, int $incorrectSteps = 0): bool
    {
        $this->logger->info(message: "Incrementing maths practice usage for learner {$learner->getId()} with question {$questionId}, isCorrect: " . ($isCorrect ? 'true' : 'false') . ", correctSteps: {$correctSteps}, incorrectSteps: {$incorrectSteps}");
        
        // Find the question
        $question = $this->entityManager->getRepository(\App\Entity\Question::class)->find($questionId);
        if (!$question) {
            throw new \Exception("Question with ID {$questionId} not found");
        }
        
        // Find or create LearnerMathsPracticeStat
        $statRepo = $this->entityManager->getRepository(\App\Entity\LearnerMathsPracticeStat::class);
        $stat = $statRepo->findOneBy([
            'learner' => $learner,
            'question' => $question
        ]);
        if (!$stat) {
            $stat = new \App\Entity\LearnerMathsPracticeStat();
            $stat->setLearner($learner);
            $stat->setQuestion($question);
            $stat->setCreated(new \DateTime());
            $this->entityManager->persist($stat);
        }
        if ($isCorrect) {
            $stat->incrementCorrect();
        } else {
            $stat->incrementIncorrect();
        }
        if ($correctSteps > 0) {
            $stat->incrementCorrectSteps($correctSteps);
        }
        if ($incorrectSteps > 0) {
            $stat->incrementIncorrectSteps($incorrectSteps);
        }
        
        // Increment daily usage counter
        $usage = $this->getOrCreateDailyUsage($learner);
        $usage->incrementMathsPractice();

        // Check if streak should be incremented
        $mathsPracticeCount = $usage->getMathsPractice();
        $today = new \DateTimeImmutable('today', new \DateTimeZone(self::TIMEZONE));
        $lastStreakUpdate = $learner->getStreakLastUpdated();
        $wasUpdatedToday = $lastStreakUpdate && $lastStreakUpdate >= $today;
        $streakEarned = false;
        if ($mathsPracticeCount >= 3 && !$wasUpdatedToday) {
            $currentStreak = $learner->getStreak();
            $learner->setStreak($currentStreak + 1);
            $learner->setStreakLastUpdated(new \DateTime());
            $this->logger->info("Streak incremented for learner {$learner->getId()} to " . ($currentStreak + 1));
            $streakEarned = true;
        }

        $this->entityManager->flush();
        return $streakEarned;
    }

    private function getOrCreateDailyUsage(Learner $learner): LearnerDailyUsage
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $today = new \DateTimeImmutable('today', $timezone);
        $usage = $this->usageRepository->findByLearnerAndDate($learner->getId(), $today);

        if (!$usage) {
            $usage = new LearnerDailyUsage();
            $usage->setLearner($learner);
            $usage->setDate($today);
            $this->entityManager->persist($usage);
        }

        return $usage;
    }

    public function hasRemainingPodcastUsage(string $learnerUid): array
    {
        $this->logger->info("Checking remaining podcast usage for learner {$learnerUid}");

        try {
            $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $timezone = new \DateTimeZone(self::TIMEZONE);
            $today = new \DateTimeImmutable('today', $timezone);
            $dailyRequests = $this->podcastRequestRepository->countDailyRequests($learner->getId(), $today);

            $subscription = $learner->getSubscription();
            $dailyLimit = $this->DAILY_PODCAST_LIMIT;

            if (
                str_contains($subscription, 'silver') ||
                str_contains($subscription, 'gold') ||
                str_contains($subscription, 'bronze')
            ) {
                $dailyLimit = 999; // Unlimited for paid subscriptions
            }

            $remainingPodcasts = $dailyLimit - $dailyRequests;

            $this->logger->info("Remaining podcasts: " . $remainingPodcasts);
            return [
                'status' => 'OK',
                'hasRemaining' => $remainingPodcasts > 0,
                'remainingCount' => $remainingPodcasts
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error checking podcast usage'
            ];
        }
    }

    public function getLearnerByUid(string $learnerUid): ?Learner
    {
        return $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
    }

    public function getLearnerMathsPracticeCount(string $learnerUid): array
    {
        $this->logger->info("Getting maths practice count for learner {$learnerUid}");

        try {
            // Find the learner
            $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Sum correct and incorrect from LearnerMathsPracticeStat
            $qb = $this->entityManager->createQueryBuilder();
            $result = $qb->select('SUM(s.correct) as total_correct, SUM(s.incorrect) as total_incorrect')
                ->from(\App\Entity\LearnerMathsPracticeStat::class, 's')
                ->where('s.learner = :learner')
                ->setParameter('learner', $learner)
                ->getQuery()
                ->getSingleResult();

            return [
                'status' => 'OK',
                'data' => [
                    'learner_uid' => $learnerUid,
                    'learner_name' => $learner->getName(),
                    'maths_practice_correct' => (int) ($result['total_correct'] ?? 0),
                    'maths_practice_incorrect' => (int) ($result['total_incorrect'] ?? 0)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error("Error getting maths practice count: " . $e->getMessage(), [
                'learnerUid' => $learnerUid,
                'exception' => $e
            ]);
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving maths practice count'
            ];
        }
    }

    /**
     * Get all practice question IDs for a learner (mathematics only), optionally filtered by topic
     */
    public function getLearnerMathsPracticeQuestionIds(string $learnerUid, ?string $topic = null): array
    {
        $this->logger->info("Getting maths practice question IDs for learner {$learnerUid}" . ($topic ? ", topic: $topic" : ''));

        try {
            $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('s')
                ->from(\App\Entity\LearnerMathsPracticeStat::class, 's')
                ->where('s.learner = :learner')
                ->setParameter('learner', $learner);

            if ($topic !== null && $topic !== '') {
                $qb->join('s.question', 'q')
                   ->andWhere('q.topic = :topic')
                   ->setParameter('topic', $topic);
            }

            $qb->orderBy('s.question', 'ASC')
               ->addOrderBy('s.created', 'DESC');

            $stats = $qb->getQuery()->getResult();

            // Keep only the latest stat per question_id
            $latestStats = [];
            foreach ($stats as $stat) {
                $questionId = $stat->getQuestion()->getId();
                if (!isset($latestStats[$questionId])) {
                    $latestStats[$questionId] = $stat;
                }
            }

            // Calculate global sums for steps
            $totalCorrectSteps = 0;
            $totalIncorrectSteps = 0;
            $questions = array_map(function($stat) use (&$totalCorrectSteps, &$totalIncorrectSteps) {
                $totalCorrectSteps += $stat->getCorrectSteps();
                $totalIncorrectSteps += $stat->getIncorrectSteps();
                return $stat->getQuestion()->getId();
            }, array_values($latestStats));

            return [
                'status' => 'OK',
                'data' => [
                    'learner_uid' => $learnerUid,
                    'learner_name' => $learner->getName(),
                    'lifetime_maths_practice_limit' => $this->LIFETIME_MATHS_PRACTICE_LIMIT,
                    'total_correct_steps' => $totalCorrectSteps,
                    'total_incorrect_steps' => $totalIncorrectSteps,
                    'maths_practice_questions' => $questions
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error getting maths practice question IDs: " . $e->getMessage(), [
                'learnerUid' => $learnerUid,
                'exception' => $e
            ]);
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving maths practice question IDs'
            ];
        }
    }

    /**
     * Reset maths practice progress for a learner and topic
     */
    public function resetMathsPracticeProgressForTopic(string $learnerUid, string $topic): array
    {
        $this->logger->info("Resetting maths practice progress for learner {$learnerUid}, topic: {$topic}");
        try {
            $learner = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }
            // Step 1: Find IDs to delete
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('s.id')
                ->from('App\\Entity\\LearnerMathsPracticeStat', 's')
                ->join('s.question', 'q')
                ->where('s.learner = :learner')
                ->andWhere('q.topic = :topic')
                ->setParameter('learner', $learner)
                ->setParameter('topic', $topic);
            $ids = array_column($qb->getQuery()->getArrayResult(), 'id');

            if (empty($ids)) {
                return [
                    'status' => 'OK',
                    'deleted_count' => 0
                ];
            }

            // Step 2: Delete by IDs
            $delQb = $this->entityManager->createQueryBuilder();
            $delQb->delete('App\\Entity\\LearnerMathsPracticeStat', 's')
                ->where($delQb->expr()->in('s.id', ':ids'))
                ->setParameter('ids', $ids);
            $deleted = $delQb->getQuery()->execute();
            return [
                'status' => 'OK',
                'deleted_count' => $deleted
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error resetting maths practice progress: " . $e->getMessage(), [
                'learnerUid' => $learnerUid,
                'topic' => $topic,
                'exception' => $e
            ]);
            return [
                'status' => 'NOK',
                'message' => 'Error resetting maths practice progress'
            ];
        }
    }

    /**
     * Get the remaining lifetime quiz count for a learner
     */
    public function getLifetimeQuizRemaining(string $learnerUid): array
    {
        $this->logger->info("Getting lifetime quiz remaining for learner {$learnerUid}");
        try {
            ${
                "status": "OK",
                "data": {
                    "quiz": 10,
                    "lesson": 10,
                    "podcast": 1,
                    "maths_practice": 5,
                    "date": "2025-07-14",
                    "lifetime_quiz_limit": 70,
                    "quizzes_taken": 0,
                    "quizzes_remaining": 70
                }
            } = $this->learnerRepository->findOneBy(['uid' => $learnerUid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }
            $learnerId = $learner->getId();
            $learnerName = $learner->getName();
            $lifetimeLimit = $this->LIFETIME_QUIZ_PRACTICE_LIMIT;
            /** @var \App\Repository\ResultRepository $resultRepo */
            $resultRepo = $this->entityManager->getRepository(\App\Entity\Result::class);
            $quizCount = $resultRepo->countByLearnerId($learnerId);
            $remaining = max(0, $lifetimeLimit - $quizCount);
            return [
                'status' => 'OK',
                'learner_uid' => $learnerUid,
                'learner_name' => $learnerName,
                'lifetime_quiz_limit' => $lifetimeLimit,
                'quizzes_taken' => $quizCount,
                'quizzes_remaining' => $remaining
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error getting lifetime quiz remaining: " . $e->getMessage(), [
                'learnerUid' => $learnerUid,
                'exception' => $e
            ]);
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving lifetime quiz remaining'
            ];
        }
    }
}