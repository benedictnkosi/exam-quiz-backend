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

    public function getLearnerBadges(Learner $learner): array
    {
        try {
            $learnerBadges = $learner->getLearnerBadges();
            $badges = [];

            foreach ($learnerBadges as $learnerBadge) {
                $badge = $learnerBadge->getBadge();
                $badges[] = [
                    'id' => $badge->getId(),
                    'name' => $badge->getName(),
                    'rules' => $badge->getRules(),
                    'earned_at' => $learnerBadge->getCreatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return [
                'status' => 'OK',
                'badges' => $badges
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in getLearnerBadges: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error fetching badges'
            ];
        }
    }

    public function getAllBadges(): array
    {
        try {
            $badges = $this->entityManager->getRepository(Badge::class)->findAll();
            $badgeList = [];

            foreach ($badges as $badge) {
                $badgeList[] = [
                    'id' => $badge->getId(),
                    'name' => $badge->getName(),
                    'rules' => $badge->getRules(),
                    'image' => $badge->getImage()
                ];
            }

            return [
                'status' => 'OK',
                'badges' => $badgeList
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in getAllBadges: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error fetching all badges'
            ];
        }
    }
}