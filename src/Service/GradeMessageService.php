<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GradeMessageService
{
    private const ANNOUNCEMENT_EMOJIS = ['📢', '📣', '🔔', '📝', '📌', '📋'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    private function ensureTitleHasEmoji(string $title): string
    {
        // Check if title already starts with an emoji
        if (preg_match('/^[\x{1F300}-\x{1F9FF}]/u', $title)) {
            return $title;
        }

        // Add a random announcement emoji
        return self::ANNOUNCEMENT_EMOJIS[array_rand(self::ANNOUNCEMENT_EMOJIS)] . ' ' . $title;
    }

    public function sendMessageToGrade(int $gradeNumber, string $title, string $message): array
    {
        try {
            // First get the grade entity by number
            $grade = $this->entityManager->getRepository(Grade::class)->findOneBy(['number' => $gradeNumber]);

            if (!$grade) {
                return [
                    'status' => 'NOK',
                    'message' => sprintf('Grade %d not found', $gradeNumber)
                ];
            }

            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
                ->from(Learner::class, 'l')
                ->andWhere('l.grade = :grade')
                ->andWhere('l.expoPushToken IS NOT NULL')
                ->andWhere('l.role = :role')
                ->setParameter('grade', $grade)
                ->setParameter('role', 'learner');

            $learners = $qb->getQuery()->getResult();
            $notificationsSent = 0;
            $errors = [];

            // Ensure title has an emoji
            $title = $this->ensureTitleHasEmoji($title);

            foreach ($learners as $learner) {
                $pushToken = $learner->getExpoPushToken();
                if (!$pushToken) {
                    continue;
                }

                $notification = [
                    'to' => $pushToken,
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'data' => [
                        'type' => 'grade_message',
                        'gradeNumber' => $gradeNumber
                    ]
                ];

                $result = $this->pushNotificationService->sendPushNotification($notification);
                if ($result['status'] === 'OK') {
                    $notificationsSent++;
                } else {
                    $errors[] = [
                        'learnerUid' => $learner->getUid(),
                        'error' => $result['message']
                    ];
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'totalLearners' => count($learners),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending grade message: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send grade message',
                'error' => $e->getMessage()
            ];
        }
    }
}