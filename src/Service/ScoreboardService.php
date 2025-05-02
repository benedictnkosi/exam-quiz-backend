<?php

namespace App\Service;

use App\Entity\Result;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ScoreboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getScoreboard(string $currentLearnerUid, string $period = 'weekly'): array
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

            // Calculate start and end of current period
            $now = new \DateTime();
            if ($period === 'daily') {
                $periodStart = (clone $now)->setTime(0, 0, 0);
                $periodEnd = (clone $now)->setTime(23, 59, 59);
            } else {
                $periodStart = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
                $periodEnd = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);
            }

            // Get all results for the current period with scoring
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l.uid, l.name, l.avatar, l.schoolName, l.publicProfile, l.followMeCode,
                        COUNT(r.id) as total_answers,
                        SUM(CASE WHEN r.outcome = \'correct\' THEN 1 ELSE -1 END) as score')
                ->from(Result::class, 'r')
                ->join('r.learner', 'l')
                ->where('r.created BETWEEN :periodStart AND :periodEnd')
                ->andWhere('l.role = :role')
                ->andWhere('l.email NOT LIKE :testEmail')
                ->andWhere('l.name NOT LIKE :testName')
                ->andWhere('l.grade = :grade')
                ->setParameter('periodStart', $periodStart)
                ->setParameter('periodEnd', $periodEnd)
                ->setParameter('role', 'learner')
                ->setParameter('testEmail', '%test%')
                ->setParameter('testName', '%test%')
                ->setParameter('grade', $currentLearner->getGrade())
                ->groupBy('l.id')
                ->having('score > 0')
                ->orderBy('score', 'DESC');

            $periodResults = $qb->getQuery()->getResult();

            // Format the response
            $scoreboard = [];
            $currentLearnerInTop10 = false;
            $currentLearnerPosition = null;

            foreach ($periodResults as $index => $result) {
                $isCurrentLearner = ($result['uid'] === $currentLearnerUid);
                if ($isCurrentLearner) {
                    $currentLearnerInTop10 = $index < 10;
                    $currentLearnerPosition = $index + 1;
                }

                if ($index < 10) {
                    $scoreboard[] = [
                        'name' => $result['name'],
                        'score' => (int) $result['score'],
                        'totalAnswers' => (int) $result['total_answers'],
                        'position' => $index + 1,
                        'isCurrentLearner' => $isCurrentLearner,
                        'avatar' => $result['avatar'],
                        'school' => $result['schoolName'],
                        'publicProfile' => $result['publicProfile'],
                        'followMeCode' => $result['followMeCode']
                    ];
                }
            }

            // If current learner is not in top 10, add them separately
            if (!$currentLearnerInTop10 && $currentLearnerPosition) {
                $currentLearnerResult = $periodResults[$currentLearnerPosition - 1];
                $scoreboard[] = [
                    'name' => $currentLearnerResult['name'],
                    'score' => (int) $currentLearnerResult['score'],
                    'totalAnswers' => (int) $currentLearnerResult['total_answers'],
                    'position' => $currentLearnerPosition,
                    'isCurrentLearner' => true,
                    'notInTop10' => true,
                    'avatar' => $currentLearnerResult['avatar'],
                    'school' => $currentLearnerResult['schoolName'],
                    'publicProfile' => $currentLearnerResult['publicProfile'],
                    'followMeCode' => $currentLearnerResult['followMeCode']
                ];
            }

            return [
                'status' => 'OK',
                'scoreboard' => $scoreboard,
                'periodStart' => $periodStart->format('Y-m-d'),
                'periodEnd' => $periodEnd->format('Y-m-d'),
                'totalParticipants' => count($periodResults),
                'period' => $period
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error fetching scoreboard: ' . $e->getMessage()
            ];
        }
    }
}