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

class LearnerDailyUsageService
{
    private const TIMEZONE = 'Africa/Johannesburg';

    private $DAILY_QUIZ_LIMIT = 10;
    private $SILVER_DAILY_QUIZ_LIMIT = 999;
    private $BRONZE_DAILY_QUIZ_LIMIT = 999;
    private $GOLD_DAILY_QUIZ_LIMIT = 999;
    private $DAILY_LESSON_LIMIT = 10;
    private $DAILY_PODCAST_LIMIT = 1;
    private $DAILY_MATHS_PRACTICE_LIMIT = 1;

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

            if (str_contains($subscription, 'silver')) {
                $this->logger->debug("Silver subscription detected - setting unlimited limits");
                $remainingQuiz = 999;
                $remainingLesson = 999;
                $remainingPodcast = 999;
                $remainingMathsPractice = 999;
            } else if (str_contains($subscription, 'gold')) {
                $this->logger->debug("Gold subscription detected - setting unlimited limits");
                $remainingQuiz = 999;
                $remainingLesson = 999;
                $remainingPodcast = 999;
                $remainingMathsPractice = 999;
            } else if (str_contains($subscription, 'bronze')) {
                $this->logger->debug("Bronze subscription detected - setting unlimited limits");
                $remainingQuiz = 999;
                $remainingLesson = 999;
                $remainingPodcast = 999;
                $remainingMathsPractice = 999;
            } else if (str_contains($subscription, 'free')) {
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
                $this->logger->warning("Unknown subscription type: {$subscription}, treating as free");
                $remainingQuiz = $this->DAILY_QUIZ_LIMIT - $usage->getQuiz();
                $remainingLesson = $this->DAILY_LESSON_LIMIT - $usage->getLesson();
                $remainingPodcast = $this->DAILY_PODCAST_LIMIT - $dailyPodcastRequests;
                $remainingMathsPractice = $this->DAILY_MATHS_PRACTICE_LIMIT - $usage->getMathsPractice();
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

    public function incrementMathsPracticeUsage(Learner $learner): void
    {
        $this->logger->info(message: "Incrementing maths practice usage for learner {$learner->getId()}");
        $usage = $this->getOrCreateDailyUsage($learner);
        $usage->incrementMathsPractice();
        $this->entityManager->flush();
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
}