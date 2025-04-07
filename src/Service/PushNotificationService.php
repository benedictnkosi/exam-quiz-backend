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
            'title' => '⏰ Quick reminder!',
            'body' => 'Take a few minutes to practice today — future you will thank you!'
        ],
        [
            'title' => '📚 Hey, don\'t forget!',
            'body' => 'A little practice goes a long way. Open the app and try 5 quick questions!'
        ],
        [
            'title' => '💪 We believe in you!',
            'body' => 'Stay on track with your goals. Your streak is waiting for you!'
        ],
        [
            'title' => '😢 You are slipping away…',
            'body' => 'Come back and practice! Even 2 minutes can make a difference.'
        ],
        [
            'title' => '😟 We\'re getting worried...',
            'body' => 'Don\'t let all your hard work fade! Open the app and pick up where you left off.'
        ],
        [
            'title' => '🔥 Your streak misses you!',
            'body' => 'It\'s not too late to get back on track. Come practice now!'
        ],
        [
            'title' => '😬 Okay... this is the last reminder!',
            'body' => 'We won\'t bother you again (for now). But your goals are still waiting — come back today!'
        ]
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getMessageForInactiveDays(int $daysInactive): array
    {
        // If days inactive is more than the number of messages, use the last message
        $messageIndex = min($daysInactive - 1, count(self::NOTIFICATION_MESSAGES) - 1);
        return self::NOTIFICATION_MESSAGES[$messageIndex];
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

            // Send welcome notification if learner was created today
            $today = new \DateTime('today');
            $createdToday = $learner->getCreated()->format('Y-m-d') === $today->format('Y-m-d');
            
            if ($createdToday && $pushToken) {
                $title = '🎉 Welcome to the Winning Team!';
                $message = 'We\'re excited to have you on board! Get ready to ace your exams with our top-notch questions and practice materials. Let\'s start learning! 💪📚';

                $this->sendNotificationsToTokens(
                    [$pushToken],
                    $message,
                    $title
                );
            }
            
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
            // Get users who were last seen less than 7 days ago and have a valid push token
            $sevenDaysAgo = new \DateTime('9 days ago');
            $today = new \DateTime('today');

            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
                ->from(Learner::class, 'l')
                ->andWhere('l.lastSeen < :today')
                ->andWhere('l.lastSeen > :sevenDaysAgo')
                ->andWhere('l.expoPushToken IS NOT NULL')
                ->andWhere('l.role = :role')
                ->setParameter('today', $today)
                ->setParameter('sevenDaysAgo', $sevenDaysAgo)
                ->setParameter('role', 'learner');

            $inactiveUsers = $qb->getQuery()->getResult();

            $notificationsSent = 0;
            $errors = [];

            foreach ($inactiveUsers as $user) {
                $pushToken = $user->getExpoPushToken();

                if (!$pushToken) {
                    continue;
                }

                // Calculate days inactive
                $lastSeen = $user->getLastSeen();
                $daysInactive = $lastSeen->diff($today)->days;

                // Get appropriate message based on days inactive
                $message = $this->getMessageForInactiveDays($daysInactive);

                $notification = [
                    'to' => $pushToken,
                    'title' => $message['title'],
                    'body' => $message['body'],
                    'sound' => 'default',
                    'data' => [
                        'type' => 'inactive_user_notification',
                        'userId' => $user->getUid(),
                        'daysInactive' => $daysInactive
                    ]
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::EXPO_API_URL);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
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
                        'statusCode' => $httpCode,
                        'daysInactive' => $daysInactive
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

    public function sendFollowNotification(Learner $follower, Learner $following): array
    {
        try {
            $pushToken = $following->getExpoPushToken();
            if (!$pushToken) {
                return [
                    'status' => 'NOK',
                    'message' => 'No push token found for the following learner'
                ];
            }

            $notification = [
                'to' => $pushToken,
                'title' => '👋 New Follower!',
                'body' => $follower->getName() . ' started following you',
                'sound' => 'default',
                'data' => [
                    'type' => 'new_follower',
                    'followerUid' => $follower->getUid(),
                    'followerName' => $follower->getName()
                ]
            ];

            return $this->sendPushNotification($notification);
        } catch (\Exception $e) {
            $this->logger->error('Error sending follow notification: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send follow notification'
            ];
        }
    }

    public function sendUnfollowNotification(Learner $follower, Learner $following): array
    {
        try {
            $pushToken = $following->getExpoPushToken();
            if (!$pushToken) {
                return [
                    'status' => 'NOK',
                    'message' => 'No push token found for the following learner'
                ];
            }

            $notification = [
                'to' => $pushToken,
                'title' => '😢 Someone Unfollowed You',
                'body' => $follower->getName() . ' unfollowed you',
                'sound' => 'default',
                'data' => [
                    'type' => 'unfollow',
                    'followerUid' => $follower->getUid(),
                    'followerName' => $follower->getName()
                ]
            ];

            return $this->sendPushNotification($notification);
        } catch (\Exception $e) {
            $this->logger->error('Error sending unfollow notification: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send unfollow notification'
            ];
        }
    }

    public function sendBlockRejectNotification(Learner $follower, Learner $following): array
    {
        try {
            $pushToken = $follower->getExpoPushToken();
            if (!$pushToken) {
                return [
                    'status' => 'NOK',
                    'message' => 'No push token found for the follower'
                ];
            }

            $notification = [
                'to' => $pushToken,
                'title' => '❌ Follow Request Rejected',
                'body' => $following->getName() . ' rejected your follow request',
                'sound' => 'default',
                'data' => [
                    'type' => 'follow_rejected',
                    'followingUid' => $following->getUid(),
                    'followingName' => $following->getName()
                ]
            ];

            return $this->sendPushNotification($notification);
        } catch (\Exception $e) {
            $this->logger->error('Error sending block/reject notification: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send block/reject notification'
            ];
        }
    }

    public function sendStreakNotification(Learner $learner, int $streak): array
    {
        try {
            $followers = $learner->getFollowers();
            $notificationsSent = 0;
            $errors = [];

            foreach ($followers as $follower) {
                $followerLearner = $follower->getFollower();
                $pushToken = $followerLearner->getExpoPushToken();
                if (!$pushToken) {
                    continue;
                }

                $notification = [
                    'to' => $pushToken,
                    'title' => '🔥 Streak Update!',
                    'body' => $learner->getName() . ' has reached a streak of ' . $streak . ' days!',
                    'sound' => 'default',
                    'data' => [
                        'type' => 'streak_update',
                        'learnerUid' => $learner->getUid(),
                        'learnerName' => $learner->getName(),
                        'streak' => $streak
                    ]
                ];

                $result = $this->sendPushNotification($notification);
                if ($result['status'] === 'OK') {
                    $notificationsSent++;
                } else {
                    $errors[] = [
                        'followerUid' => $followerLearner->getUid(),
                        'error' => $result['message']
                    ];
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'totalFollowers' => count($followers),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending streak notifications: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send streak notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendBadgeNotification(Learner $learner, string $badgeName): array
    {
        try {
            $followers = $learner->getFollowers();
            $notificationsSent = 0;
            $errors = [];

            foreach ($followers as $follower) {
                $followerLearner = $follower->getFollower();
                $pushToken = $followerLearner->getExpoPushToken();
                if (!$pushToken) {
                    continue;
                }

                $notification = [
                    'to' => $pushToken,
                    'title' => '🏆 New Badge Earned!',
                    'body' => $learner->getName() . ' has earned the ' . $badgeName . ' badge!',
                    'sound' => 'default',
                    'data' => [
                        'type' => 'badge_earned',
                        'learnerUid' => $learner->getUid(),
                        'learnerName' => $learner->getName(),
                        'badgeName' => $badgeName
                    ]
                ];

                $result = $this->sendPushNotification($notification);
                if ($result['status'] === 'OK') {
                    $notificationsSent++;
                } else {
                    $errors[] = [
                        'followerUid' => $followerLearner->getUid(),
                        'error' => $result['message']
                    ];
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'totalFollowers' => count($followers),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending badge notifications: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send badge notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendNotificationsToTokens(array $pushTokens, string $message, string $title = 'New Message'): array
    {
        try {
            $notificationsSent = 0;
            $errors = [];

            foreach ($pushTokens as $token) {
                if (empty($token)) {
                    continue;
                }

                $notification = [
                    'to' => $token,
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'data' => [
                        'type' => 'custom_message'
                    ]
                ];

                $result = $this->sendPushNotification($notification);
                if ($result['status'] === 'OK') {
                    $notificationsSent++;
                } else {
                    $errors[] = [
                        'token' => $token,
                        'error' => $result['message']
                    ];
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'totalTokens' => count($pushTokens),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending notifications to tokens: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendPushNotification(array $notification): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::EXPO_API_URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return [
                    'status' => 'OK',
                    'message' => 'Notification sent successfully'
                ];
            }

            $this->logger->error('Failed to send notification: ' . $response);
            return [
                'status' => 'NOK',
                'message' => 'Failed to send notification',
                'error' => $response
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending push notification: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ];
        }
    }
}