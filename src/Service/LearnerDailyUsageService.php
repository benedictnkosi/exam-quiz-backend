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

    private $DAILY_QUIZ_LIMIT = 15;
    private $SILVER_DAILY_QUIZ_LIMIT = 50;
    private $BRONZE_DAILY_QUIZ_LIMIT = 30;
    private $GOLD_DAILY_QUIZ_LIMIT = 100;
    private $DAILY_LESSON_LIMIT = 15;
    private $DAILY_PODCAST_LIMIT = 15;

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

            // Get today's date
            $today = new \DateTimeImmutable();

            // Get or create today's usage record
            $usage = $this->usageRepository->findByLearnerAndDate($learner->getId(), $today);
            if (!$usage) {
                $usage = new LearnerDailyUsage();
                $usage->setLearner($learner);
                $usage->setDate($today);
                $this->entityManager->persist($usage);
                $this->entityManager->flush();
            }

            // Get podcast usage from podcast requests
            $dailyPodcastRequests = $this->podcastRequestRepository->countDailyRequests($learner->getId(), $today);

            $subscription = $learner->getSubscription();
            $remainingQuiz = 0;
            $remainingLesson = 0;
            $remainingPodcast = 0;
            if (str_contains($subscription, 'silver')) {
                $remainingQuiz = $this->SILVER_DAILY_QUIZ_LIMIT - $usage->getQuiz();
                $remainingLesson = 999;
                $remainingPodcast = 999;
            } else if (str_contains($subscription, 'gold')) {
                $remainingQuiz = $this->GOLD_DAILY_QUIZ_LIMIT - $usage->getQuiz();
                $remainingLesson = 999;
                $remainingPodcast = 999;
            } else if (str_contains($subscription, 'bronze')) {
                $remainingQuiz = $this->BRONZE_DAILY_QUIZ_LIMIT - $usage->getQuiz();
                $remainingLesson = 999;
                $remainingPodcast = 999;
            } else if (str_contains($subscription, 'free')) {
                $remainingQuiz = $this->DAILY_QUIZ_LIMIT - $usage->getQuiz();
                $remainingLesson = $this->DAILY_LESSON_LIMIT - $usage->getLesson();
                $remainingPodcast = $this->DAILY_PODCAST_LIMIT - $dailyPodcastRequests;
            }

            return [
                'status' => 'OK',
                'data' => [
                    'quiz' => $remainingQuiz,
                    'lesson' => $remainingLesson,
                    'podcast' => $remainingPodcast,
                    'date' => $usage->getDate()->format('Y-m-d')
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
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

        $today = new \DateTimeImmutable('today');

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
        $podcastRequest->setRequestedAt(new \DateTimeImmutable());

        $this->entityManager->persist($podcastRequest);
        $this->entityManager->flush();
    }

    private function getOrCreateDailyUsage(Learner $learner): LearnerDailyUsage
    {
        $today = new \DateTimeImmutable('today');
        $usage = $this->usageRepository->findByLearnerAndDate($learner->getId(), $today);

        if (!$usage) {
            $usage = new LearnerDailyUsage();
            $usage->setLearner($learner);
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

            $today = new \DateTimeImmutable();
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
}