<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerReading;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerPromotionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function checkAndPromoteLearner(string $learnerUid): array
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

            // Get the last 3 completed readings
            $lastReadings = $this->entityManager->getRepository(LearnerReading::class)
                ->createQueryBuilder('lr')
                ->join('lr.chapter', 'b')
                ->where('lr.learner = :learner')
                ->andWhere('lr.status = :status')
                ->setParameter('learner', $learner)
                ->setParameter('status', 'completed')
                ->orderBy('lr.date', 'DESC')
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            // Check if we have 3 readings
            if (count($lastReadings) < 3) {
                return [
                    'status' => 'NOK',
                    'message' => 'Need 3 completed readings to check for promotion'
                ];
            }

            // Check if all readings meet the criteria
            $allReadingsMeetCriteria = true;
            foreach ($lastReadings as $reading) {
                $book = $reading->getChapter();
                $duration = $reading->getDuration();
                $score = $reading->getScore();
                $expectedDuration = $book->getReadingDuration();

                // Check if reading duration is below expected and score is 100
                if ($duration > $expectedDuration || $score !== 100) {
                    $allReadingsMeetCriteria = false;
                    break;
                }
            }

            if ($allReadingsMeetCriteria) {
                // Promote the learner
                $currentLevel = $learner->getReadingLevel();
                $learner->setReadingLevel($currentLevel + 1);

                $this->entityManager->persist($learner);
                $this->entityManager->flush();

                return [
                    'status' => 'OK',
                    'message' => 'Learner promoted to level ' . ($currentLevel + 1),
                    'data' => [
                        'previousLevel' => $currentLevel,
                        'newLevel' => $currentLevel + 1
                    ]
                ];
            }

            return [
                'status' => 'NOK',
                'message' => 'Learner does not meet promotion criteria'
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error checking learner promotion: ' . $e->getMessage()
            ];
        }
    }
}