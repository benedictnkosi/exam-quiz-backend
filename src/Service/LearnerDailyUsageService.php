<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerDailyUsage;
use App\Repository\LearnerDailyUsageRepository;
use App\Repository\LearnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerDailyUsageService
{

    private $DAILY_QUIZ_LIMIT = 20;
    private $DAILY_LESSON_LIMIT = 15;
    private $DAILY_PODCAST_LIMIT = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LearnerRepository $learnerRepository,
        private readonly LearnerDailyUsageRepository $usageRepository,
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
            $today->setTime(0, 0, 0);

            // Get or create today's usage record
            $usage = $this->usageRepository->findByLearnerAndDate($learner->getId(), $today);
            if (!$usage) {
                $usage = new LearnerDailyUsage();
                $usage->setLearner($learner);
                $usage->setDate($today);
                $this->entityManager->persist($usage);
                $this->entityManager->flush();
            }

            return [
                'status' => 'OK',
                'data' => [
                    'quiz' => $this->DAILY_QUIZ_LIMIT - $usage->getQuiz(),
                    'lesson' => $this->DAILY_LESSON_LIMIT - $usage->getLesson(),
                    'podcast' => $this->DAILY_PODCAST_LIMIT - $usage->getPodcast(),
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

    public function incrementPodcastUsage(Learner $learner): void
    {
        $this->logger->info("Incrementing podcast usage for learner {$learner->getId()}");
        $usage = $this->getOrCreateDailyUsage($learner);
        $usage->incrementPodcast();
        $this->entityManager->flush();
    }

    private function getOrCreateDailyUsage(Learner $learner): LearnerDailyUsage
    {
        $today = new \DateTimeImmutable('today');
        $usage = $this->usageRepository->findByLearnerAndDate($learner->getId(), $today);

        if (!$usage) {
            $usage = new LearnerDailyUsage();
            $usage->setLearner($learner->getId());
            $this->entityManager->persist($usage);
        }

        return $usage;
    }

    public function hasRemainingPodcastUsage(string $learnerUid): array
    {
        $this->logger->info("Checking remaining podcast usage for learner {$learnerUid}");

        $usageData = $this->getDailyUsageByLearnerUid($learnerUid);

        if ($usageData['status'] === 'NOK') {
            return $usageData;
        }

        $remainingPodcasts = $usageData['data']['podcast'];

        return [
            'status' => 'OK',
            'hasRemaining' => $remainingPodcasts > 0,
            'remainingCount' => $remainingPodcasts
        ];
    }
}