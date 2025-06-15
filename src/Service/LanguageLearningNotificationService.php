<?php

namespace App\Service;

use App\Entity\LanguageLearnerProgress;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LanguageLearningNotificationService
{
    private const NOTIFICATION_MESSAGES = [
        [
            'title' => '🎯 Quick Practice Time!',
            'body' => 'Just 5 minutes of practice can keep your language skills fresh!'
        ],
        [
            'title' => '📚 Daily Practice Reminder',
            'body' => 'A little practice each day goes a long way in language learning.'
        ],
        [
            'title' => '💪 Keep Your Streak Alive!',
            'body' => 'Don\'t break your learning streak! Come back for a quick practice.'
        ],
        [
            'title' => '🌟 Your Progress Awaits!',
            'body' => 'Don\'t let your hard work fade. Continue your language learning journey today!'
        ],
        [
            'title' => '🎓 Stay on Track!',
            'body' => 'Every day of practice brings you closer to fluency. Come back and learn!'
        ],
        [
            'title' => '📖 Learning Time!',
            'body' => 'Your language skills are waiting for you. Come back and practice!'
        ],
        [
            'title' => '🎯 One Week Check-in!',
            'body' => 'It\'s been a week! Time to refresh your language skills with some practice.'
        ]
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendProgressNotifications(): array
    {
        try {
            $now = new \DateTime();
            $oneDayAgo = (clone $now)->modify('-1 day');
            $sevenDaysAgo = (clone $now)->modify('-7 days');

            // Get learners who haven't completed a lesson in the last 1-7 days
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('DISTINCT l')
                ->from(Learner::class, 'l')
                ->join(LanguageLearnerProgress::class, 'llp', 'WITH', 'llp.learner = l')
                ->where('llp.lastUpdate < :oneDayAgo')
                ->andWhere('llp.lastUpdate > :sevenDaysAgo')
                ->andWhere('l.expoPushToken IS NOT NULL')
                ->andWhere('l.role = :role')
                ->setParameter('oneDayAgo', $oneDayAgo)
                ->setParameter('sevenDaysAgo', $sevenDaysAgo)
                ->setParameter('role', 'learner');

            $learners = $qb->getQuery()->getResult();
            $notificationsSent = 0;
            $errors = [];

            foreach ($learners as $learner) {
                $pushToken = $learner->getExpoPushToken();
                if (!$pushToken) {
                    continue;
                }

                // Get the last progress update
                $lastProgress = $this->entityManager->getRepository(LanguageLearnerProgress::class)
                    ->findOneBy(['learner' => $learner], ['lastUpdate' => 'DESC']);

                if (!$lastProgress) {
                    continue;
                }

                // Calculate days since last activity
                $daysInactive = $lastProgress->getLastUpdate()->diff($now)->days;

                // Get appropriate message based on days inactive (0-6 days maps to 0-6 in array)
                $messageIndex = min($daysInactive - 1, count(self::NOTIFICATION_MESSAGES) - 1);
                $message = self::NOTIFICATION_MESSAGES[$messageIndex];

                $notification = [
                    'to' => $pushToken,
                    'title' => $message['title'],
                    'body' => $message['body'],
                    'sound' => 'default',
                    'data' => [
                        'type' => 'language_learning_reminder',
                        'learnerUid' => $learner->getUid(),
                        'daysInactive' => $daysInactive,
                        'language' => $lastProgress->getLanguage()
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
            $this->logger->error('Error sending language learning notifications: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send language learning notifications',
                'error' => $e->getMessage()
            ];
        }
    }
}