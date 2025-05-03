<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TopLearnerNotificationService
{
    private const TIMEZONE = 'Africa/Johannesburg';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushNotificationService,
        private readonly BadgeService $badgeService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendTopLearnerNotifications(): array
    {
        try {
            $timezone = new \DateTimeZone(self::TIMEZONE);
            $yesterday = new \DateTime('yesterday', $timezone);
            $today = new \DateTime('today', $timezone);
            $notificationsSent = 0;
            $errors = [];

            // Get top learners for each grade (1, 2, 3)
            for ($gradeId = 1; $gradeId <= 3; $gradeId++) {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('l', 'COUNT(r.id) as points')
                    ->from(Learner::class, 'l')
                    ->join(Result::class, 'r', 'WITH', 'r.learner = l.id')
                    ->where('l.grade = :gradeId')
                    ->andWhere('r.created >= :yesterday')
                    ->andWhere('r.created < :today')
                    ->andWhere('r.outcome = :correct')
                    ->andWhere('l.expoPushToken IS NOT NULL')
                    ->groupBy('l.id')
                    ->orderBy('points', 'DESC')
                    ->setMaxResults(1)
                    ->setParameter('gradeId', $gradeId)
                    ->setParameter('yesterday', $yesterday)
                    ->setParameter('today', $today)
                    ->setParameter('correct', 'correct');

                $result = $qb->getQuery()->getResult();

                if (!empty($result)) {
                    $topLearner = $result[0][0];
                    $points = $result[0]['points'];

                    if ($points > 0) {
                        // Assign Daily Goat badge
                        $this->badgeService->assignBadge($topLearner, 'Daily Goat');

                        $notification = [
                            'to' => $topLearner->getExpoPushToken(),
                            'title' => '🏆 Top Learner Badge!',
                            'body' => sprintf(
                                'Congratulations! You were the top learner in Grade %d yesterday with %d correct answers!',
                                $gradeId,
                                $points
                            ),
                            'sound' => 'default',
                            'data' => [
                                'type' => 'top_learner',
                                'gradeId' => $gradeId,
                                'points' => $points,
                                'date' => $yesterday->format('Y-m-d')
                            ]
                        ];

                        $result = $this->pushNotificationService->sendPushNotification($notification);
                        if ($result['status'] === 'OK') {
                            $notificationsSent++;
                        } else {
                            $errors[] = [
                                'learnerUid' => $topLearner->getUid(),
                                'error' => $result['message']
                            ];
                        }
                    }
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending top learner notifications: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send top learner notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendLastWeekTopLearnerNotifications(): array
    {
        try {
            $timezone = new \DateTimeZone(self::TIMEZONE);
            $now = new \DateTime('now', $timezone);

            // Get last week's Monday and Sunday
            $lastWeekMonday = clone $now;
            $lastWeekMonday->modify('last week monday');
            $lastWeekSunday = clone $lastWeekMonday;
            $lastWeekSunday->modify('+6 days');

            $notificationsSent = 0;
            $errors = [];

            // Get top learners for each grade (1, 2, 3)
            for ($gradeId = 1; $gradeId <= 3; $gradeId++) {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('l', 'COUNT(r.id) as points')
                    ->from(Learner::class, 'l')
                    ->join(Result::class, 'r', 'WITH', 'r.learner = l.id')
                    ->where('l.grade = :gradeId')
                    ->andWhere('r.created >= :startDate')
                    ->andWhere('r.created <= :endDate')
                    ->andWhere('r.outcome = :correct')
                    ->andWhere('l.expoPushToken IS NOT NULL')
                    ->groupBy('l.id')
                    ->orderBy('points', 'DESC')
                    ->setMaxResults(1)
                    ->setParameter('gradeId', $gradeId)
                    ->setParameter('startDate', $lastWeekMonday)
                    ->setParameter('endDate', $lastWeekSunday)
                    ->setParameter('correct', 'correct');

                $result = $qb->getQuery()->getResult();

                if (!empty($result)) {
                    $topLearner = $result[0][0];
                    $points = $result[0]['points'];

                    if ($points > 0) {
                        // Assign Weekly Goat badge
                        $this->badgeService->assignBadge($topLearner, 'Weekly Goat');

                        $notification = [
                            'to' => $topLearner->getExpoPushToken(),
                            'title' => '🏆 Weekly Top Learner Badge!',
                            'body' => sprintf(
                                'Congratulations! You were the top learner in Grade %d last week with %d correct answers!',
                                $gradeId,
                                $points
                            ),
                            'sound' => 'default',
                            'data' => [
                                'type' => 'weekly_top_learner',
                                'gradeId' => $gradeId,
                                'points' => $points,
                                'startDate' => $lastWeekMonday->format('Y-m-d'),
                                'endDate' => $lastWeekSunday->format('Y-m-d')
                            ]
                        ];

                        $result = $this->pushNotificationService->sendPushNotification($notification);
                        if ($result['status'] === 'OK') {
                            $notificationsSent++;
                        } else {
                            $errors[] = [
                                'learnerUid' => $topLearner->getUid(),
                                'error' => $result['message']
                            ];
                        }
                    }
                }
            }

            return [
                'status' => 'OK',
                'notificationsSent' => $notificationsSent,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending last week top learner notifications: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Failed to send last week top learner notifications',
                'error' => $e->getMessage()
            ];
        }
    }
}