<?php

namespace App\Controller;

use App\Service\UserBehaviorReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reports')]
class UserBehaviorReportController extends AbstractController
{
    private UserBehaviorReportService $reportService;

    public function __construct(UserBehaviorReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    #[Route('/user-behavior', name: 'api_reports_user_behavior', methods: ['GET'])]
    public function getUserBehavior(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 20);
            // Ensure limit is between 1 and 100
            $limit = max(1, min($limit, 100));

            $data = $this->reportService->getTopUsersDailyResults($limit);
            return $this->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'limit' => $limit
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/user-behavior/{id}', name: 'api_reports_user_behavior_single', methods: ['GET'])]
    public function getSingleUserBehavior(int $id): JsonResponse
    {
        try {
            $data = $this->reportService->getDailyResultCounts($id);
            return $this->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}