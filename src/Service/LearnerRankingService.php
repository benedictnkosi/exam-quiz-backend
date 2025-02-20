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

            // Get ALL learners with their scores
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l.uid, l.name, l.score')
                ->from(Learner::class, 'l')
                ->orderBy('l.score', 'DESC');

            $allLearners = $qb->getQuery()->getResult();

            // Create position map for all learners
            $positionMap = [];
            $position = 1;
            $lastScore = null;
            $skipPositions = 0;

            foreach ($allLearners as $index => $learner) {
                $score = round($learner['score'] ?? 0, 2);

                if ($lastScore !== null && $lastScore !== $score) {
                    $position += $skipPositions + 1;
                    $skipPositions = 0;
                } else if ($lastScore === $score) {
                    $skipPositions++;
                }

                $positionMap[$learner['uid']] = [
                    'position' => $position,
                    'score' => $score
                ];

                $lastScore = $score;
            }

            // Get top 10 only
            $topLearners = array_slice($allLearners, 0, 10);

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
                    'score' => $positionMap[$learner['uid']]['score'],
                    'position' => $positionMap[$learner['uid']]['position'],
                    'isCurrentLearner' => $isCurrentLearner
                ];
            }

            // If current learner is not in top 10, add them to the response separately
            if (!$currentLearnerInTop10) {
                $rankings[] = [
                    'name' => $currentLearner->getName(),
                    'score' => $positionMap[$currentLearnerUid]['score'],
                    'position' => $positionMap[$currentLearnerUid]['position'],
                    'isCurrentLearner' => true,
                    'notInTop10' => true
                ];
            }

            return [
                'status' => 'OK',
                'rankings' => $rankings,
                'currentLearnerScore' => $positionMap[$currentLearnerUid]['score'],
                'currentLearnerPosition' => $positionMap[$currentLearnerUid]['position'],
                'totalLearners' => count($allLearners)
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