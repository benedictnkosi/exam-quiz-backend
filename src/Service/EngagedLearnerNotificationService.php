<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EngagedLearnerNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendNotificationsToEngagedLearners(): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];

        try {
            // Get learners who have answered more than 10 questions this week and have a push token
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
                ->from(Learner::class, 'l')
                ->where('l.expoPushToken IS NOT NULL')
                ->andWhere('l.lastSeen >= :startOfWeek')
                ->setParameter('startOfWeek', new \DateTime('monday this week'));

            $learners = $qb->getQuery()->getResult();

            foreach ($learners as $learner) {
                try {
                    // Check if learner has answered more than 10 questions this week
                    $questionCount = $this->getQuestionCountForLearner($learner);
                    
                    if ($questionCount < 10) {
                        $results['skipped'][] = [
                            'learner' => $learner->getUid(),
                            'reason' => 'Less than 10 questions answered this week'
                        ];
                        continue;
                    }

                    // Send notification
                    $notification = [
                        'title' => '🌟 Help us improve!',
                        'body' => sprintf('You\'ve answered %d questions this week! Would you take a moment to rate our app? Your feedback helps us make it even better!', $questionCount)
                    ];

                    $pushResult = $this->pushNotificationService->sendNotificationsToTokens(
                        [$learner->getExpoPushToken()],
                        $notification['body'],
                        $notification['title']
                    );

                    if ($pushResult['success']) {
                        $results['success'][] = $learner->getUid();
                    } else {
                        $results['failed'][] = [
                            'learner' => $learner->getUid(),
                            'error' => $pushResult['error'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing learner notification', [
                        'learner' => $learner->getUid(),
                        'error' => $e->getMessage()
                    ]);
                    $results['failed'][] = [
                        'learner' => $learner->getUid(),
                        'error' => $e->getMessage()
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in sendNotificationsToEngagedLearners', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $results;
    }

    private function getQuestionCountForLearner(Learner $learner): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(r.id)')
            ->from(Result::class, 'r')
            ->where('r.learner = :learner')
            ->andWhere('r.created >= :startOfWeek')
            ->setParameter('learner', $learner)
            ->setParameter('startOfWeek', new \DateTime('monday this week'));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
} 