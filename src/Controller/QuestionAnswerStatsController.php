<?php

namespace App\Controller;

use App\Service\QuestionAnswerStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class QuestionAnswerStatsController extends AbstractController
{
    public function __construct(
        private QuestionAnswerStatsService $questionAnswerStatsService
    ) {
    }

    #[Route('/stats/daily-answers', name: 'daily_answer_stats', methods: ['GET'])]
    public function getDailyAnswerStats(): JsonResponse
    {
        $result = $this->questionAnswerStatsService->getDailyAnswerStats();
        return $this->json($result);
    }
}