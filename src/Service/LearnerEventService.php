<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerEventService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getUpcomingEventsWithReminders(string $learnerUid): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $events = $learner->getEvents() ?? [];
            $upcomingEvents = [];
            $timezone = new \DateTimeZone('Africa/Johannesburg');
            $today = new \DateTime('now', $timezone);
            $threeDaysFromNow = (new \DateTime('now', $timezone))->modify('+3 days');

            foreach ($events as $date => $dayEvents) {
                $this->logger->info("Event: " . json_encode($events));
                $eventDate = new \DateTime($date, $timezone);

                // Compare only the date part (Y-m-d) for filtering
                $eventDateOnly = $eventDate->format('Y-m-d');
                $todayDateOnly = $today->format('Y-m-d');
                $threeDaysFromNowDateOnly = $threeDaysFromNow->format('Y-m-d');

                // Skip if event date is more than 3 days from now
                if ($eventDateOnly > $threeDaysFromNowDateOnly) {
                    $this->logger->info("Skipping because event date is more than 3 days from now");
                    continue;
                }

                // Skip if event date is in the past
                if ($eventDateOnly < $todayDateOnly) {
                    $this->logger->info("Skipping because event date is in the past");
                    continue;
                }

                foreach ($dayEvents as $event) {

                    if ($event['reminder'] === true) {
                        $upcomingEvents[] = [
                            'date' => $date,
                            'title' => $event['title'],
                            'startTime' => $event['startTime'],
                            'endTime' => $event['endTime'],
                            'subject' => $event['subject']
                        ];
                    }
                }
            }

            // Sort events by date and time
            usort($upcomingEvents, function ($a, $b) {
                $dateCompare = strcmp($a['date'], $b['date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcmp($a['startTime'], $b['startTime']);
            });

            return [
                'status' => 'OK',
                'events' => $upcomingEvents
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting upcoming events: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving upcoming events: ' . $e->getMessage()
            ];
        }
    }

    public function getTodaysEvents(string $id): array
    {
        try {
            // Try to find learner by uid first
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $id]);

            // If not found by uid, try by followMeCode
            if (!$learner) {
                $learner = $this->entityManager->getRepository(Learner::class)
                    ->findOneBy(['followMeCode' => $id]);
            }

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $events = $learner->getEvents() ?? [];
            $today = (new \DateTime())->format('Y-m-d');
            $todaysEvents = [];

            if (isset($events[$today])) {
                foreach ($events[$today] as $event) {
                    $todaysEvents[] = [
                        'title' => $event['title'],
                        'startTime' => $event['startTime'],
                        'endTime' => $event['endTime'],
                        'subject' => $event['subject'],
                        'reminder' => $event['reminder'] ?? false
                    ];
                }
            }

            return [
                'status' => 'OK',
                'date' => $today,
                'events' => $todaysEvents
            ];

        } catch (\Exception $e) {
            $this->logger->error("Error getting today's events: " . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting today\'s events: ' . $e->getMessage()
            ];
        }
    }
}