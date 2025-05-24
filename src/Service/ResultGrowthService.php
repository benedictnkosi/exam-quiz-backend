<?php

namespace App\Service;

use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class ResultGrowthService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calculate daily growth in results for the past 2 weeks
     * 
     * @return array Array of daily results with date and count
     */
    public function calculateDailyGrowth(): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('date', 'date');
        $rsm->addScalarResult('count', 'count');

        $query = $this->entityManager->createNativeQuery(
            'SELECT 
                DATE(created) as date,
                COUNT(*) as count
            FROM result
            WHERE created > "2025-05-24"
            GROUP BY DATE(created)
            ORDER BY date DESC',
            $rsm
        );

        return $query->getResult();
    }

    /**
     * Calculate growth percentage between two consecutive days for the past 2 weeks
     * 
     * @return array Array of daily results with date, count and growth percentage
     */
    public function calculateDailyGrowthWithPercentage(): array
    {
        $dailyResults = $this->calculateDailyGrowth();
        $resultsWithGrowth = [];

        for ($i = 0; $i < count($dailyResults); $i++) {
            $currentDay = $dailyResults[$i];
            $previousDay = $dailyResults[$i + 1] ?? null;

            $resultsWithGrowth[] = [
                'date' => $currentDay['date'],
                'count' => $currentDay['count'],
                'growth_percentage' => $this->calculateGrowthPercentage($currentDay['count'], $previousDay['count'] ?? null)
            ];
        }

        return $resultsWithGrowth;
    }

    private function calculateGrowthPercentage(int $currentCount, ?int $previousCount): ?float
    {
        // If there's no previous day, return null
        if ($previousCount === null) {
            return null;
        }

        // If previous day had 0 results and current day has results, it's 100% growth
        if ($previousCount === 0 && $currentCount > 0) {
            return 100.0;
        }

        // If previous day had 0 results and current day also has 0, growth is 0%
        if ($previousCount === 0 && $currentCount === 0) {
            return 0.0;
        }

        // Calculate percentage growth
        $growth = (($currentCount - $previousCount) / $previousCount) * 100;

        // Round to 2 decimal places for cleaner output
        return round($growth, 2);
    }
}