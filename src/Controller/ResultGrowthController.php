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
            'data' => $dailyGrowth
        ]);
    }

    #[Route('/daily/with-percentage', name: 'api_result_growth_daily_with_percentage', methods: ['GET'])]
    public function getDailyGrowthWithPercentage(): JsonResponse
    {
        $growthWithPercentage = $this->resultGrowthService->calculateDailyGrowthWithPercentage();
        
        return $this->json([
            'status' => 'success',
            'data' => $growthWithPercentage
        ]);
    }
} 