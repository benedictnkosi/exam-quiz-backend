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
        private LoggerInterface $logger,
        private PushNotificationService $pushNotificationService
    ) {
    }

    public function checkAndAssignBadgesToAllLearners(): void
    {
        $learners = $this->entityManager->getRepository(Learner::class)->findAll();
        foreach ($learners as $learner) {
            $this->checkAndAssignBadges($learner);
        }
    }
    public function checkAndAssignBadges(Learner $learner): array
    {
        try {
            $newBadges = [];

            // Check subject-specific badges
            $subjects = [
                'Physical Sciences',
                'Mathematics',
                'Agricultural Sciences',
                'Economics',
                'Geography',
                'Life Sciences',
                'Mathematics Literacy',
                'History',
                'Tourism',
                'Business Studies',
                'Accounting'
            ];

            foreach ($subjects as $subject) {
                if ($this->checkSubjectPerformance($learner, $subject)) {
                    $this->logger->info('subject deserves a badge: ' . $subject);
                    $badgeName = $subject;
                    $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => $badgeName]);
                    if ($badge && !$this->hasLearnerBadge($learner, $badgeName)) {
                        $this->logger->info('assigning badge: ' . $badgeName);
                        $this->assignBadge($learner, $badgeName);
                        $newBadges[] = $this->formatBadge($badge);
                    }
                }
            }

            // Check if learner is the top in their grade
            $grade = $learner->getGrade();
            if ($grade) {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('l')
                    ->from(Learner::class, 'l')
                    ->where('l.grade = :grade')
                    ->andWhere('l.points > 0')
                    ->andWhere('l.role = :role')
                    ->orderBy('l.points', 'DESC')
                    ->setMaxResults(1)
                    ->setParameter('grade', $grade)
                    ->setParameter('role', 'learner');

                $topLearner = $qb->getQuery()->getOneOrNullResult();

                if ($topLearner && $topLearner->getId() === $learner->getId()) {
                    $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => 'All Time Goat']);
                    if ($badge && !$this->hasLearnerBadge($learner, 'All Time Goat')) {
                        $this->assignBadge($learner, 'All Time Goat');
                        $newBadges[] = $this->formatBadge($badge);
                    }
                }
            }

            // Check streak badges
            $streak = $learner->getStreak();
            $this->logger->info('Streak: ' . $streak);
            if ($streak >= 3) {
                $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => '3-Day Streak']);
                if ($badge && !$this->hasLearnerBadge($learner, '3-Day Streak')) {
                    $this->assignBadge($learner, '3-Day Streak');
                    $newBadges[] = $this->formatBadge($badge);
                }
            }
            if ($streak >= 7) {
                $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => '7-Day Streak']);
                if ($badge && !$this->hasLearnerBadge($learner, '7-Day Streak')) {
                    $this->assignBadge($learner, '7-Day Streak');
                    $newBadges[] = $this->formatBadge($badge);
                }
            }
            if ($streak >= 30) {
                $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => '30-Day Streak']);
                if ($badge && !$this->hasLearnerBadge($learner, '30-Day Streak')) {
                    $this->assignBadge($learner, '30-Day Streak');
                    $newBadges[] = $this->formatBadge($badge);
                }
            }

            // Check consecutive correct answers badges
            $consecutiveCorrect = $this->getConsecutiveCorrectAnswers($learner);
            if ($consecutiveCorrect >= 3) {
                $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => '3 in a row']);
                if ($badge && !$this->hasLearnerBadge($learner, '3 in a row')) {
                    $this->assignBadge($learner, '3 in a row');
                    $newBadges[] = $this->formatBadge($badge);
                }
            }
            if ($consecutiveCorrect >= 5) {
                $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => '5 in a row']);
                if ($badge && !$this->hasLearnerBadge($learner, '5 in a row')) {
                    $this->assignBadge($learner, '5 in a row');
                    $newBadges[] = $this->formatBadge($badge);
                }
            }
            if ($consecutiveCorrect >= 10) {
                $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['name' => '10 in a row']);
                if ($badge && !$this->hasLearnerBadge($learner, '10 in a row')) {
                    $this->assignBadge($learner, '10 in a row');
                    $newBadges[] = $this->formatBadge($badge);
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

    public function assignBadge(Learner $learner, string $badgeName): void
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
            $this->entityManager->flush();

            // Send notification to followers
            $this->pushNotificationService->sendBadgeNotification($learner, $badgeName);
        }
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
                if ($badge === null) {
                    continue; // Skip if badge is null
                }
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

    private function formatBadge(Badge $badge): array
    {
        return [
            'id' => $badge->getId(),
            'name' => $badge->getName(),
            'rules' => $badge->getRules(),
            'image' => $badge->getImage()
        ];
    }

    private function checkSubjectPerformance(Learner $learner, string $subject): bool
    {
        // First get the subject IDs for the given subject name
        $subjectQb = $this->entityManager->createQueryBuilder();
        $subjectQb->select('s.id')
            ->from('App\Entity\Subject', 's')
            ->where('s.name LIKE :subjectName')
            ->setParameter('subjectName', '%' . $subject . '%');

        $subjectIds = array_column($subjectQb->getQuery()->getResult(), 'id');

        if (empty($subjectIds)) {
            return false;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(r) as total_questions')
            ->addSelect('SUM(CASE WHEN r.outcome = :correct THEN 1 ELSE 0 END) as correct_answers')
            ->from(Result::class, 'r')
            ->join('r.question', 'q')
            ->join('q.subject', 's')
            ->where('r.learner = :learner')
            ->andWhere('s.id IN (:subjectIds)')
            ->setParameter('learner', $learner)
            ->setParameter('subjectIds', $subjectIds)
            ->setParameter('correct', 'correct');

        $result = $qb->getQuery()->getOneOrNullResult();

        $this->logger->info('count: ' . $result['total_questions']);
        if (!$result || $result['total_questions'] < 50) {
            return false;
        }

        $passRate = ($result['correct_answers'] / $result['total_questions']) * 100;
        $this->logger->info('passRate: ' . $passRate);
        return $passRate >= 80;
    }
}