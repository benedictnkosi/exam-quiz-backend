<?php

namespace App\Controller;

use App\Service\LearnerRankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LeaderboardController extends AbstractController
{
    private LearnerRankingService $leaderboardService;

    public function __construct(LearnerRankingService $leaderboardService)
    {
        $this->leaderboardService = $leaderboardService;
    }

    #[Route('/api/leaderboard', name: 'api_leaderboard', methods: ['GET'])]
    public function getLeaderboard(Request $request): JsonResponse
    {
        $uid = $request->query->get('uid');
        $result = $this->leaderboardService->getTopLearnersWithCurrentPosition($uid);

        return $this->json([
            'success' => true,
            'data' => $result
        ]);
    }
} 