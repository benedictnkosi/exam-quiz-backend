<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Learnersubjects;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerRankingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getTopLearnersWithCurrentPosition(string $currentLearnerUid): array
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

            // Get top 10 learners with their average scores
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l.uid, l.name, l.score')
                ->from(Learner::class, 'l')
                ->groupBy('l.id')
                ->orderBy('l.score', 'DESC')
                ->setMaxResults(10);

            $topLearners = $qb->getQuery()->getResult();

            // Get current learner's average score
            $currentLearnerQb = $this->entityManager->createQueryBuilder();
            $currentLearnerQb->select('l.score')
                ->from(Learner::class, 'l')
                ->where('l.uid = :learner')
                ->setParameter('learner', $currentLearnerUid);

            $currentLearnerScore = $currentLearnerQb->getQuery()->getSingleScalarResult() ?? 0;

            // Format the response
            $rankings = [];
            $currentLearnerInTop10 = false;

            foreach ($topLearners as $learner) {
                $isCurrentLearner = ($learner['uid'] === $currentLearnerUid);
                if ($isCurrentLearner) {
                    $currentLearnerInTop10 = true;
                }

                $rankings[] = [
                    'name' => $learner['name'],
                    'score' => round($learner['score'] ?? 0, 2),
                    'isCurrentLearner' => $isCurrentLearner
                ];
            }

            // If current learner is not in top 10, add them to the response separately
            if (!$currentLearnerInTop10) {
                $rankings[] = [
                    'name' => $currentLearner->getName(),
                    'score' => round($currentLearnerScore, 2),
                    'isCurrentLearner' => true,
                    'notInTop10' => true
                ];
            }

            return [
                'status' => 'OK',
                'rankings' => $rankings,
                'currentLearnerScore' => round($currentLearnerScore, 2)
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error fetching rankings: ' . $e->getMessage()
            ];
        }
    }
}