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
            $today = new \DateTime();
            $threeDaysFromNow = (new \DateTime())->modify('+3 days');

            foreach ($events as $date => $dayEvents) {
                $eventDate = new \DateTime($date);

                // Skip if event date is more than 3 days from now
                if ($eventDate > $threeDaysFromNow) {
                    continue;
                }

                // Skip if event date is in the past
                if ($eventDate < $today) {
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
}