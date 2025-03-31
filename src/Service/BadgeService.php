<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Learner;
use App\Entity\LearnerBadge;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BadgeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function checkAndAssignBadges(Learner $learner): array
    {
        try {
            $newBadges = [];

            // Check streak badges
            $streak = $learner->getStreak();
            $this->logger->info('Streak: ' . $streak);
            if ($streak >= 3) {

                if (!$this->hasLearnerBadge($learner, '3-Day Streak')) {
                    $this->assignBadge($learner, '3-Day Streak');
                    $newBadges[] = '3-Day Streak';
                }
            }
            if ($streak >= 7) {

                if (!$this->hasLearnerBadge($learner, '7-Day Streak')) {
                    $this->assignBadge($learner, '7-Day Streak');
                    $newBadges[] = '7-Day Streak';
                }
            }
            if ($streak >= 30) {

                if (!$this->hasLearnerBadge($learner, '30-Day Streak')) {
                    $this->assignBadge($learner, '30-Day Streak');
                    $newBadges[] = '30-Day Streak';
                }
            }

            // Check consecutive correct answers badges
            $consecutiveCorrect = $this->getConsecutiveCorrectAnswers($learner);
            if ($consecutiveCorrect >= 5) {

                if (!$this->hasLearnerBadge($learner, '5 in a row')) {
                    $this->assignBadge($learner, '5 in a row');
                    $newBadges[] = '5 in a row';
                }
            }
            if ($consecutiveCorrect >= 10) {

                if (!$this->hasLearnerBadge($learner, '10 in a row')) {
                    $this->assignBadge($learner, '10 in a row');
                    $newBadges[] = '10 in a row';
                }
            }

            return [
                'status' => 'OK',
                'new_badges' => $newBadges
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in checkAndAssignBadges: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error checking and assigning badges'
            ];
        }
    }

    private function assignBadge(Learner $learner, string $badgeName): void
    {
        // Check if badge exists
        $badge = $this->entityManager->getRepository(Badge::class)
            ->findOneBy(['name' => $badgeName]);

        if (!$badge) {
            // Create new badge if it doesn't exist
            $badge = new Badge();
            $badge->setName($badgeName);
            $badge->setRules($this->getBadgeRules($badgeName));
            $this->entityManager->persist($badge);
        }

        // Check if learner already has this badge
        $existingLearnerBadge = $this->entityManager->getRepository(LearnerBadge::class)
            ->findOneBy([
                'learner' => $learner,
                'badge' => $badge
            ]);

        if (!$existingLearnerBadge) {
            $learnerBadge = new LearnerBadge();
            $learnerBadge->setLearner($learner);
            $learnerBadge->setBadge($badge);
            $this->entityManager->persist($learnerBadge);
        }

        $this->entityManager->flush();
    }

    private function getBadgeRules(string $badgeName): string
    {
        return match ($badgeName) {
            '3-Day Streak' => 'Maintain a 3-day streak',
            '7-Day Streak' => 'Maintain a 7-day streak',
            '30-Day Streak' => 'Maintain a 30-day streak',
            '5 in a row' => 'Answer 5 questions correctly in a row',
            '10 in a row' => 'Answer 10 questions correctly in a row',
            default => ''
        };
    }

    private function getConsecutiveCorrectAnswers(Learner $learner): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
            ->from(Result::class, 'r')
            ->where('r.learner = :learner')
            ->orderBy('r.created', 'DESC')
            ->setParameter('learner', $learner);

        $results = $qb->getQuery()->getResult();
        $consecutiveCount = 0;

        foreach ($results as $result) {
            if ($result->getOutcome() === 'correct') {
                $consecutiveCount++;
            } else {
                // Break the streak when we encounter an incorrect answer
                break;
            }
        }

        return $consecutiveCount;
    }

    private function hasLearnerBadge(Learner $learner, string $badgeName): bool
    {
        $badge = $this->entityManager->getRepository(Badge::class)
            ->findOneBy(['name' => $badgeName]);

        if (!$badge) {
            return false;
        }

        $existingLearnerBadge = $this->entityManager->getRepository(LearnerBadge::class)
            ->findOneBy([
                'learner' => $learner,
                'badge' => $badge
            ]);

        return $existingLearnerBadge !== null;
    }
}