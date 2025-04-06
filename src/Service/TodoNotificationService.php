<?php

namespace App\Service;

use App\Entity\Todo;
use App\Entity\Learner;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TodoNotificationService
{
    private const NOTIFICATION_MESSAGES = [
        'today' => [
            'title' => '📝 Todo Due Today!',
            'body' => 'You have a todo due today: %s'
        ],
        'tomorrow' => [
            'title' => '📝 Todo Due Tomorrow!',
            'body' => 'You have a todo due tomorrow: %s'
        ],
        'three_days' => [
            'title' => '📝 Todo Due in 3 Days!',
            'body' => 'You have a todo due in 3 days: %s'
        ]
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TodoRepository $todoRepository,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendDueDateNotifications(): array
    {
        try {
            $today = new \DateTime('today');
            $tomorrow = new \DateTime('tomorrow');
            $threeDaysFromNow = new \DateTime('+3 days');

            // Get todos due today
            $todosDueToday = $this->todoRepository->findByDueDate($today);
            $this->sendNotificationsForTodos($todosDueToday, 'today');

            // Get todos due tomorrow
            $todosDueTomorrow = $this->todoRepository->findByDueDate($tomorrow);
            $this->sendNotificationsForTodos($todosDueTomorrow, 'tomorrow');

            // Get todos due in 3 days
            $todosDueInThreeDays = $this->todoRepository->findByDueDate($threeDaysFromNow);
            $this->sendNotificationsForTodos($todosDueInThreeDays, 'three_days');

            return [
                'success' => true,
                'message' => 'Todo notifications sent successfully'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error sending todo notifications: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send todo notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendNotificationsForTodos(array $todos, string $timeframe): void
    {
        foreach ($todos as $todo) {
            $learner = $todo->getLearner();
            $pushToken = $learner->getExpoPushToken();

            if (!$pushToken) {
                continue;
            }

            $message = self::NOTIFICATION_MESSAGES[$timeframe];
            $notification = [
                'to' => $pushToken,
                'title' => $message['title'],
                'body' => sprintf($message['body'], $todo->getTitle()),
                'sound' => 'default',
                'data' => [
                    'type' => 'todo_due_date',
                    'todoId' => $todo->getId(),
                    'dueDate' => $todo->getDueDate()->format('Y-m-d'),
                    'timeframe' => $timeframe
                ]
            ];

            $this->pushNotificationService->sendPushNotification($notification);
        }
    }
} 