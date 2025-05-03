<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InactiveLearnerNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendNotificationsToInactiveLearners(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Get learners who have never answered any questions
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
                ->from(Learner::class, 'l')
                ->where('l.id NOT IN (
                    SELECT IDENTITY(r.learner) 
                    FROM App\Entity\Result r
                )')
                ->andWhere('l.expoPushToken IS NOT NULL')
                ->andWhere('l.role = :role')
                ->andWhere('DATE(l.createdAt) != CURRENT_DATE()')
                ->andWhere('l.createdAt >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)')
                ->setParameter('role', 'learner');

            $inactiveLearners = $qb->getQuery()->getResult();

            $notificationsSent = 0;
            $errors = [];

            foreach ($inactiveLearners as $learner) {
                $pushToken = $learner->getExpoPushToken();

                if (!$pushToken) {
                    continue;
                }

                $this->logger->info('Sending notification to inactive learner ' . $learner->getUid());

                $notification = [
                    'to' => $pushToken,
                    'title' => '🚀 Answer your first question!',
                    'body' => 'Start your learning journey today by answering your first question 💪',
                    'sound' => 'default',
                    'data' => [
                        'type' => 'first_question_reminder',
                        'userId' => $learner->getUid()
                    ]
                ];

                $result = $this->pushNotificationService->sendPushNotification($notification);

                if ($result['status'] === 'OK') {
                    $notificationsSent++;
                } else {
                    $errors[] = [
                        'userId' => $learner->getUid(),
                        'error' => $result['message'] ?? 'Unknown error'
                    ];
                    $this->logger->error('Failed to send notification to learner ' . $learner->getUid() . ': ' . ($result['message'] ?? 'Unknown error'));
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'totalInactiveLearners' => count($inactiveLearners),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending notifications to inactive learners: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ];
        }
    }
}