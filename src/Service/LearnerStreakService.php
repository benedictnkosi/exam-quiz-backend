<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerStreak;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerStreakService
{
    private const REQUIRED_DAILY_QUESTIONS = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function trackQuestionAnswered(string $learnerUid): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $streak = $this->entityManager->getRepository(LearnerStreak::class)
                ->findOneBy(['learner' => $learner]);

            if (!$streak) {
                $streak = new LearnerStreak();
                $streak->setLearner($learner);
            }

            // Check if we need to reset or update the streak
            $this->updateStreakStatus($streak);

            // Increment questions answered today
            $streak->setQuestionsAnsweredToday($streak->getQuestionsAnsweredToday() + 1);
            $streak->setLastAnsweredAt(new \DateTime());

            // Check if daily goal is met
            if ($streak->getQuestionsAnsweredToday() === self::REQUIRED_DAILY_QUESTIONS) {
                $streak->setCurrentStreak($streak->getCurrentStreak() + 1);
                
                if ($streak->getCurrentStreak() > $streak->getLongestStreak()) {
                    $streak->setLongestStreak($streak->getCurrentStreak());
                }
            }

            $this->entityManager->persist($streak);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'data' => [
                    'currentStreak' => $streak->getCurrentStreak(),
                    'longestStreak' => $streak->getLongestStreak(),
                    'questionsAnsweredToday' => $streak->getQuestionsAnsweredToday(),
                    'questionsNeededToday' => max(0, self::REQUIRED_DAILY_QUESTIONS - $streak->getQuestionsAnsweredToday()),
                    'streakMaintained' => $streak->getQuestionsAnsweredToday() >= self::REQUIRED_DAILY_QUESTIONS
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error tracking streak: ' . $e->getMessage()
            ];
        }
    }

    private function updateStreakStatus(LearnerStreak $streak): void
    {
        $today = new \DateTime('today');
        $lastUpdate = $streak->getLastStreakUpdateDate()->format('Y-m-d');
        $todayDate = $today->format('Y-m-d');

        if ($lastUpdate !== $todayDate) {
            // Check if yesterday's goal was met
            $yesterday = (new \DateTime('yesterday'))->format('Y-m-d');
            if ($lastUpdate === $yesterday && $streak->getQuestionsAnsweredToday() < self::REQUIRED_DAILY_QUESTIONS) {
                // Reset streak if yesterday's goal wasn't met
                $streak->setCurrentStreak(0);
            }

            // Reset daily counter for new day
            $streak->setQuestionsAnsweredToday(0);
            $streak->setLastStreakUpdateDate($today);
        }
    }

    public function getLearnerStreakInfo(string $learnerUid): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $streak = $this->entityManager->getRepository(LearnerStreak::class)
                ->findOneBy(['learner' => $learner]);

            if (!$streak) {
                return [
                    'status' => 'OK',
                    'data' => [
                        'currentStreak' => 0,
                        'longestStreak' => 0,
                        'questionsAnsweredToday' => 0,
                        'questionsNeededToday' => self::REQUIRED_DAILY_QUESTIONS,
                        'streakMaintained' => false
                    ]
                ];
            }

            $this->updateStreakStatus($streak);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'data' => [
                    'currentStreak' => $streak->getCurrentStreak(),
                    'longestStreak' => $streak->getLongestStreak(),
                    'questionsAnsweredToday' => $streak->getQuestionsAnsweredToday(),
                    'questionsNeededToday' => max(0, self::REQUIRED_DAILY_QUESTIONS - $streak->getQuestionsAnsweredToday()),
                    'streakMaintained' => $streak->getQuestionsAnsweredToday() >= self::REQUIRED_DAILY_QUESTIONS
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting streak info: ' . $e->getMessage()
            ];
        }
    }
} 