<?php

namespace App\Controller;

use App\Service\LearnerRegistrationStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class LearnerRegistrationStatsController extends AbstractController
{
    public function __construct(
        private LearnerRegistrationStatsService $learnerRegistrationStatsService
    ) {
    }

    #[Route('/stats/daily-registrations', name: 'daily_registration_stats', methods: ['GET'])]
    public function getDailyRegistrationStats(): JsonResponse
    {
        $result = $this->learnerRegistrationStatsService->getDailyRegistrationStats();
        return $this->json($result);
    }

    #[Route('/stats/total-learners', name: 'total_learners', methods: ['GET'])]
    public function getTotalLearners(): JsonResponse
    {
        $result = $this->learnerRegistrationStatsService->getTotalLearners();
        return $this->json($result);
    }
}