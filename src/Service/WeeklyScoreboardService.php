<?php

namespace App\Service;

use App\Entity\Result;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WeeklyScoreboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getWeeklyScoreboard(string $currentLearnerUid): array
    {
        try {
            // Get current learner
            $currentLearner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $currentLearnerUid]);

            if (!$currentLearner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Current learner not found'
                ];
            }

            // Calculate start and end of current week
            $now = new \DateTime();
            $weekStart = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
            $weekEnd = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);

            // Get all results for the current week with scoring
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l.uid, l.name, l.avatar, 
                        COUNT(r.id) as total_answers,
                        SUM(CASE WHEN r.outcome = \'correct\' THEN 1 ELSE -1 END) as score')
               ->from(Result::class, 'r')
               ->join('r.learner', 'l')
               ->where('r.created BETWEEN :weekStart AND :weekEnd')
               ->andWhere('l.role = :role')
               ->andWhere('l.email NOT LIKE :testEmail')
               ->andWhere('l.name NOT LIKE :testName')
               ->andWhere('l.grade = :grade')
               ->setParameter('weekStart', $weekStart)
               ->setParameter('weekEnd', $weekEnd)
               ->setParameter('role', 'learner')
               ->setParameter('testEmail', '%test%')
               ->setParameter('testName', '%test%')
               ->setParameter('grade', $currentLearner->getGrade())
               ->groupBy('l.id')
               ->having('score > 0')
               ->orderBy('score', 'DESC');

            $weeklyResults = $qb->getQuery()->getResult();

            // Format the response
            $scoreboard = [];
            $currentLearnerInTop10 = false;
            $currentLearnerPosition = null;

            foreach ($weeklyResults as $index => $result) {
                $isCurrentLearner = ($result['uid'] === $currentLearnerUid);
                if ($isCurrentLearner) {
                    $currentLearnerInTop10 = $index < 10;
                    $currentLearnerPosition = $index + 1;
                }

                if ($index < 10) {
                    $scoreboard[] = [
                        'name' => $result['name'],
                        'score' => (int)$result['score'],
                        'totalAnswers' => (int)$result['total_answers'],
                        'position' => $index + 1,
                        'isCurrentLearner' => $isCurrentLearner,
                        'avatar' => $result['avatar']
                    ];
                }
            }

            // If current learner is not in top 10, add them separately
            if (!$currentLearnerInTop10 && $currentLearnerPosition) {
                $currentLearnerResult = $weeklyResults[$currentLearnerPosition - 1];
                $scoreboard[] = [
                    'name' => $currentLearnerResult['name'],
                    'score' => (int)$currentLearnerResult['score'],
                    'totalAnswers' => (int)$currentLearnerResult['total_answers'],
                    'position' => $currentLearnerPosition,
                    'isCurrentLearner' => true,
                    'notInTop10' => true,
                    'avatar' => $currentLearnerResult['avatar']
                ];
            }

            return [
                'status' => 'OK',
                'scoreboard' => $scoreboard,
                'weekStart' => $weekStart->format('Y-m-d'),
                'weekEnd' => $weekEnd->format('Y-m-d'),
                'totalParticipants' => count($weeklyResults)
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error fetching weekly scoreboard: ' . $e->getMessage()
            ];
        }
    }
} 