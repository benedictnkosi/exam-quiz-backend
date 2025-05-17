<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class UserBehaviorReportService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get top users with highest number of results
     * @param int $limit Number of users to return
     * @return array Array of users with their result counts
     */
    public function getTopUsersByResults(int $limit = 20): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->select('l.id', 'l.name', 'COUNT(r.id) as resultCount')
            ->from(Learner::class, 'l')
            ->leftJoin(Result::class, 'r', Join::WITH, 'r.learner = l.id')
            ->groupBy('l.id', 'l.name')
            ->orderBy('resultCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get daily result counts for a specific user
     * @param int $learnerId
     * @return array Array of daily result counts
     */
    public function getDailyResultCounts(int $learnerId): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Get the learner's registration date
        $learner = $this->entityManager->getRepository(Learner::class)->find($learnerId);
        if (!$learner) {
            throw new \Exception('Learner not found');
        }

        $registrationDate = $learner->getCreated();

        // Get all results for this learner
        $results = $qb->select('r.created')
            ->from(Result::class, 'r')
            ->where('r.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->orderBy('r.created', 'ASC')
            ->getQuery()
            ->getResult();

        // Calculate days since registration for each result
        $dailyCounts = [];
        foreach ($results as $result) {
            $resultDate = $result['created'];
            $daysSinceRegistration = $registrationDate->diff($resultDate)->days;

            if (!isset($dailyCounts[$daysSinceRegistration])) {
                $dailyCounts[$daysSinceRegistration] = 0;
            }
            $dailyCounts[$daysSinceRegistration]++;
        }

        // Format the results
        $formattedResults = [];
        foreach ($dailyCounts as $day => $count) {
            $formattedResults[] = [
                'day' => $day,
                'count' => $count
            ];
        }

        return $formattedResults;
    }

    /**
     * Get daily result counts for top users
     * @param int $limit Number of users to return
     * @return array Array of users with their daily result counts
     */
    public function getTopUsersDailyResults(int $limit = 20): array
    {
        $topUsers = $this->getTopUsersByResults($limit);
        $result = [];

        foreach ($topUsers as $user) {
            $result[] = [
                'userId' => $user['id'],
                'name' => $user['name'],
                'totalResults' => $user['resultCount'],
                'dailyResults' => $this->getDailyResultCounts($user['id'])
            ];
        }

        return $result;
    }
}