<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TodoMigrationService
{
    private const DEFAULT_START_TIME = '09:00';
    private const DEFAULT_END_TIME = '10:00';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Migrate future dated todos to learner events for all learners
     * 
     * @return array Result of the migration
     */
    public function migrateAllTodosToEvents(): array
    {
        try {
            $this->logger->info("Starting todo migration for all learners");

            // Get all learners with todos
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l', 't')
                ->from(Learner::class, 'l')
                ->leftJoin('l.todos', 't')
                ->where('t.id IS NOT NULL');

            $learners = $qb->getQuery()->getResult();

            if (empty($learners)) {
                return [
                    'status' => 'OK',
                    'message' => 'No learners with todos found',
                    'migrated_count' => 0
                ];
            }

            $totalMigrated = 0;
            $today = new \DateTime();
            $today->setTime(0, 0, 0);

            foreach ($learners as $learner) {
                $currentEvents = $learner->getEvents() ?? [];
                $migratedCount = 0;

                foreach ($learner->getTodos() as $todo) {
                    // Skip if todo has no due date or is in the past
                    if (!$todo->getDueDate() || $todo->getDueDate() < $today) {
                        continue;
                    }

                    $dueDate = $todo->getDueDate()->format('Y-m-d');
                    $event = [
                        'title' => $todo->getTitle(),
                        'startTime' => self::DEFAULT_START_TIME,
                        'endTime' => self::DEFAULT_END_TIME,
                        'subject' => $todo->getSubjectName() ?? 'General',
                        'reminder' => true
                    ];

                    // Initialize the date array if it doesn't exist
                    if (!isset($currentEvents[$dueDate])) {
                        $currentEvents[$dueDate] = [];
                    }

                    // Add the event
                    $currentEvents[$dueDate][] = $event;
                    $migratedCount++;
                    $totalMigrated++;

                    // Remove the todo
                    //$this->entityManager->remove($todo);
                }

                if ($migratedCount > 0) {
                    // Update learner's events only if we migrated any todos
                    $learner->setEvents($currentEvents);
                    $this->logger->info("Migrated {$migratedCount} todos to events for learner: " . $learner->getUid());
                }
            }

            // Flush all changes at once
            $this->entityManager->flush();

            $this->logger->info("Successfully migrated {$totalMigrated} todos to events across all learners");

            return [
                'status' => 'OK',
                'message' => 'Successfully migrated todos to events',
                'migrated_count' => $totalMigrated,
                'affected_learners' => count($learners)
            ];

        } catch (\Exception $e) {
            $this->logger->error("Error migrating todos: " . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error migrating todos: ' . $e->getMessage()
            ];
        }
    }
}