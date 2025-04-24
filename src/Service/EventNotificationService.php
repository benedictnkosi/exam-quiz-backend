<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EventNotificationService
{
    private const NOTIFICATION_MESSAGES = [
        'tomorrow' => [
            'title' => '📚 %s due tomorrow!',
            'body' => '%s at %s'
        ],
        'three_days' => [
            'title' => '📚 %s due in 3 Days!',
            'body' => '%s at %s'
        ]
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendEventNotifications(): array
    {
        try {
            $tomorrow = new \DateTime('tomorrow');
            $threeDaysFromNow = new \DateTime('+3 days');

            // Get all learners
            $learners = $this->entityManager->getRepository(Learner::class)->findAll();

            foreach ($learners as $learner) {
                $events = $learner->getEvents() ?? [];

                // Process events for tomorrow
                $tomorrowEvents = $this->getEventsForDate($events, $tomorrow->format('Y-m-d'));
                $this->sendNotificationsForEvents($learner, $tomorrowEvents, 'tomorrow');

                // Process events for 3 days from now
                $threeDaysEvents = $this->getEventsForDate($events, $threeDaysFromNow->format('Y-m-d'));
                $this->sendNotificationsForEvents($learner, $threeDaysEvents, 'three_days');
            }

            return [
                'success' => true,
                'message' => 'Event notifications sent successfully'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending event notifications: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send event notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    private function getEventsForDate(array $events, string $date): array
    {
        return $events[$date] ?? [];
    }

    private function sendNotificationsForEvents(Learner $learner, array $events, string $timeframe): void
    {
        $pushToken = $learner->getExpoPushToken();

        if (!$pushToken) {
            return;
        }

        foreach ($events as $event) {
            if (!$event['reminder']) {
                continue;
            }

            $message = self::NOTIFICATION_MESSAGES[$timeframe];
            $notification = [
                'to' => $pushToken,
                'title' => sprintf($message['title'], $event['subject']),
                'body' => sprintf(
                    $message['body'],
                    $event['title'],
                    $event['startTime']
                ),
                'sound' => 'default',
                'data' => [
                    'type' => 'event_reminder',
                    'subject' => $event['subject'],
                    'date' => $timeframe,
                    'startTime' => $event['startTime'],
                    'endTime' => $event['endTime']
                ]
            ];

            $this->pushNotificationService->sendPushNotification($notification);
        }
    }
}