<?php

namespace App\Controller;

use App\Service\QuestionStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class QuestionStatsController extends AbstractController
{
    public function __construct(
        private QuestionStatsService $questionStatsService
    ) {
    }

    #[Route('/stats/questions/total', name: 'total_questions', methods: ['GET'])]
    public function getTotalQuestions(): JsonResponse
    {
        $result = $this->questionStatsService->getTotalQuestions();
        return $this->json($result);
    }

    #[Route('/stats/questions', name: 'question_stats', methods: ['GET'])]
    public function getQuestionStats(): JsonResponse
    {
        $fromDate = (new \DateTime())->modify('-30 days')->format('Y-m-d');
        $endDate = (new \DateTime())->format('Y-m-d');

        $result = $this->questionStatsService->getQuestionStats($fromDate, $endDate);
        return $this->json($result);
    }
}