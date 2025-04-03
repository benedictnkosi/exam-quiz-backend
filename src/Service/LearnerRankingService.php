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

            // Get ALL learners with their points
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l.uid, l.name, l.points')
                ->from(Learner::class, 'l')
                ->orderBy('l.points', 'DESC');

            $allLearners = $qb->getQuery()->getResult();

            // Create position map for all learners
            $positionMap = [];
            $position = 1;
            $lastPoints = null;
            $skipPositions = 0;

            foreach ($allLearners as $index => $learner) {
                $points = round($learner['points'] ?? 0, 2);

                if ($lastPoints !== null && $lastPoints !== $points) {
                    $position += $skipPositions + 1;
                    $skipPositions = 0;
                } else if ($lastPoints === $points) {
                    $skipPositions++;
                }

                $positionMap[$learner['uid']] = [
                    'position' => $position,
                    'points' => $points
                ];

                $lastPoints = $points;
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
                    'points' => $positionMap[$learner['uid']]['points'],
                    'position' => $positionMap[$learner['uid']]['position'],
                    'isCurrentLearner' => $isCurrentLearner
                ];
            }

            // If current learner is not in top 10, add them to the response separately
            if (!$currentLearnerInTop10) {
                $rankings[] = [
                    'name' => $currentLearner->getName(),
                    'points' => $positionMap[$currentLearnerUid]['points'],
                    'position' => $positionMap[$currentLearnerUid]['position'],
                    'isCurrentLearner' => true,
                    'notInTop10' => true
                ];
            }

            return [
                'status' => 'OK',
                'rankings' => $rankings,
                'currentLearnerPoints' => $positionMap[$currentLearnerUid]['points'],
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