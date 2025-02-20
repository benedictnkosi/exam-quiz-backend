<?php

namespace App\Controller;

use App\Service\LearnerStreakService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/streaks')]
class LearnerStreakController extends AbstractController
{
    public function __construct(
        private LearnerStreakService $learnerStreakService
    ) {}

    #[Route('/track/{uid}', methods: ['POST'])]
    public function trackQuestion(string $uid): JsonResponse
    {
        $result = $this->learnerStreakService->trackQuestionAnswered($uid);
        return $this->json($result);
    }

    #[Route('/{uid}', methods: ['GET'])]
    public function getStreakInfo(string $uid): JsonResponse
    {
        $result = $this->learnerStreakService->getLearnerStreakInfo($uid);
        return $this->json($result);
    }
} 