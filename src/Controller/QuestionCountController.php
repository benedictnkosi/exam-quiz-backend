<?php

namespace App\Controller;

use App\Service\QuestionCountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/questions')]
class QuestionCountController extends AbstractController
{
    public function __construct(
        private QuestionCountService $questionCountService
    ) {
    }

    #[Route('/count/{subjectId}', name: 'get_question_counts', methods: ['GET'])]
    public function getQuestionCounts(int $subjectId): JsonResponse
    {
        $counts = $this->questionCountService->getQuestionCountsByYearAndTerm($subjectId);

        return $this->json([
            'status' => 'OK',
            'data' => $counts
        ]);
    }
}