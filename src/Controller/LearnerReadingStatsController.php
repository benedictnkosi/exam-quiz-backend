<?php

namespace App\Controller;

use App\Service\LearnerReadingStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LearnerReadingStatsController extends AbstractController
{
    public function __construct(
        private readonly LearnerReadingStatsService $statsService
    ) {
    }

    #[Route('/api/reading-stats/completed-chapters', name: 'app_reading_stats_completed_chapters', methods: ['GET'])]
    public function getCompletedChaptersCount(): JsonResponse
    {
        $stats = $this->statsService->getCompletedChaptersCountByDay();

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}