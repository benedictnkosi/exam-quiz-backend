<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    private const EXPO_API_URL = 'https://exp.host/--/api/v2/push/send';

    private const NOTIFICATION_MESSAGES = [
        [
            'title' => 'Keep your streak alive!',
            'body' => 'You\'re on a roll! Answer a few questions today and keep that momentum going.'
        ],
        [
            'title' => 'Small practice, big results!',
            'body' => 'Just 5 questions today can make a huge difference. Let\'s do this!'
        ],
        [
            'title' => 'Your goals are waiting!',
            'body' => 'Stay consistent — open the app and practice now to stay on track.'
        ],
        [
            'title' => 'Almost there!',
            'body' => 'Every question you answer brings you closer to success. Don\'t break your streak!'
        ],
        [
            'title' => 'You\'ve got this!',
            'body' => 'A few minutes of practice today keeps exam stress away. Start now!'
        ]
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getRandomMessage(): array
    {
        return self::NOTIFICATION_MESSAGES[array_rand(self::NOTIFICATION_MESSAGES)];
    }

    public function updatePushToken(string $uid, string $pushToken): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $learner->setExpoPushToken($pushToken);
            $this->entityManager->persist($learner);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Push token updated successfully'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error updating push token: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to update push token'
            ];
        }
    }

    public function sendNotificationsToInactiveUsers(): array
    {
        try {
            // Get users who were last seen yesterday and have a valid push token
            $yesterday = new \DateTime('yesterday');
            $today = new \DateTime('today');

            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
                ->from(Learner::class, 'l')
                ->andWhere('l.lastSeen < :today')
                ->andWhere('l.expoPushToken IS NOT NULL')
                ->andWhere('l.email = :email')
                ->setParameter('today', $today)
                ->setParameter('email', 'nkosi@gmail.com');

            $inactiveUsers = $qb->getQuery()->getResult();

            $notificationsSent = 0;
            $errors = [];

            foreach ($inactiveUsers as $user) {
                $pushToken = $user->getExpoPushToken();

                if (!$pushToken) {
                    continue;
                }

                $randomMessage = $this->getRandomMessage();
                $message = [
                    'to' => $pushToken,
                    'title' => $randomMessage['title'],
                    'body' => $randomMessage['body'],
                    'sound' => 'default',
                    'data' => [
                        'type' => 'inactive_user_notification',
                        'userId' => $user->getUid()
                    ]
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::EXPO_API_URL);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $notificationsSent++;
                } else {
                    $errors[] = [
                        'userId' => $user->getUid(),
                        'error' => $response,
                        'statusCode' => $httpCode
                    ];
                    $this->logger->error('Failed to send notification to user ' . $user->getUid() . ': ' . $response);
                }
            }

            return [
                'success' => true,
                'notificationsSent' => $notificationsSent,
                'totalInactiveUsers' => count($inactiveUsers),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending notifications: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ];
        }
    }
}