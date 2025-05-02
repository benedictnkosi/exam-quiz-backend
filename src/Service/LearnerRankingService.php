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
            $qb->select('l.uid, l.name, l.points, l.avatar, l.schoolName, l.publicProfile, l.followMeCode')
                ->from(Learner::class, 'l')
                ->where('l.points > 0')
                ->andWhere('l.role = :role')
                ->andWhere('l.email NOT LIKE :testEmail')
                ->andWhere('l.name NOT LIKE :testName')
                ->andWhere('l.grade = :grade')
                ->setParameter('role', 'learner')
                ->setParameter('testEmail', '%test%')
                ->setParameter('testName', '%test%')
                ->setParameter('grade', $currentLearner->getGrade())
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
            $currentLearnerPosition = null;
            $currentLearnerPoints = $currentLearner->getPoints();

            foreach ($topLearners as $learner) {
                $isCurrentLearner = ($learner['uid'] === $currentLearnerUid);
                if ($isCurrentLearner) {
                    $currentLearnerInTop10 = true;
                    $currentLearnerPosition = $positionMap[$learner['uid']]['position'];
                }

                $rankings[] = [
                    'name' => $learner['name'],
                    'points' => $positionMap[$learner['uid']]['points'],
                    'position' => $positionMap[$learner['uid']]['position'],
                    'isCurrentLearner' => $isCurrentLearner,
                    'avatar' => $learner['avatar'],
                    'school' => $learner['schoolName'],
                    'publicProfile' => $learner['publicProfile'],
                    'followMeCode' => $learner['followMeCode']
                ];
            }

            // If current learner is not in top 10 and has points, add them to the response separately
            if (!$currentLearnerInTop10 && $currentLearnerPoints > 0) {
                $currentLearnerPosition = $positionMap[$currentLearnerUid]['position'] ?? count($allLearners) + 1;
                $rankings[] = [
                    'name' => $currentLearner->getName(),
                    'points' => $currentLearnerPoints,
                    'position' => $currentLearnerPosition,
                    'isCurrentLearner' => true,
                    'notInTop10' => true,
                    'avatar' => $currentLearner->getAvatar(),
                    'publicProfile' => $currentLearner->getPublicProfile(),
                    'followMeCode' => $currentLearner->getFollowMeCode()
                ];
            }

            return [
                'status' => 'OK',
                'rankings' => $rankings,
                'currentLearnerPoints' => $currentLearnerPoints,
                'currentLearnerPosition' => $currentLearnerPosition,
                'totalLearners' => count($allLearners),
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