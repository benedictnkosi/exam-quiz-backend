<?php

namespace App\Controller;

use App\Service\ResultGrowthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/result-growth')]
class ResultGrowthController extends AbstractController
{
    public function __construct(
        private readonly ResultGrowthService $resultGrowthService
    ) {
    }

    #[Route('/daily', name: 'api_result_growth_daily', methods: ['GET'])]
    public function getDailyGrowth(): JsonResponse
    {
        $dailyGrowth = $this->resultGrowthService->calculateDailyGrowth();
        
        return $this->json([
            'status' => 'success',
            'message' => 'Daily result counts for the past 2 weeks. Use /daily/with-percentage for growth rates.',
            'data' => $dailyGrowth
        ]);
    }

    #[Route('/daily/with-percentage', name: 'api_result_growth_daily_with_percentage', methods: ['GET'])]
    public function getDailyGrowthWithPercentage(): JsonResponse
    {
        $growthWithPercentage = $this->resultGrowthService->calculateDailyGrowthWithPercentage();
        
        // Format the response to show growth trends
        $formattedData = array_map(function($day) {
            $growth = $day['growth_percentage'];
            $trend = match(true) {
                $growth === null => 'N/A',
                $growth > 0 => '↑',
                $growth < 0 => '↓',
                default => '→'
            };
            
            return [
                'date' => $day['date'],
                'count' => $day['count'],
                'growth_percentage' => $growth,
                'trend' => $trend
            ];
        }, $growthWithPercentage);
        
        return $this->json([
            'status' => 'success',
            'message' => 'Daily result counts with growth percentage for the past 2 weeks',
            'data' => $formattedData
        ]);
    }
} 