<?php

namespace App\Controller;

use App\Service\LearnerDailyUsageStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LearnerDailyUsageStatsController extends AbstractController
{
    public function __construct(
        private readonly LearnerDailyUsageStatsService $statsService
    ) {
    }

    #[Route('/api/usage-stats/free-users-high-activity', name: 'app_usage_stats_free_users_high_activity', methods: ['GET'])]
    public function getFreeUsersWithHighActivity(): JsonResponse
    {
        $stats = $this->statsService->getFreeUsersWithHighActivity();

        return $this->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'description' => 'Number of free users who completed 10 or more questions or lessons per day for the past 2 weeks'
            ]
        ]);
    }
}