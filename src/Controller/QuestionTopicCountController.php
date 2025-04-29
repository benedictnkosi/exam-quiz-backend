<?php

namespace App\Controller;

use App\Service\QuestionTopicCountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class QuestionTopicCountController extends AbstractController
{
    #[Route('/api/subjects/{subjectName}/topic-question-counts', name: 'get_question_counts_per_topic', methods: ['GET'])]
    public function getQuestionCountsPerTopic(
        string $subjectName,
        Request $request,
        QuestionTopicCountService $questionTopicCountService
    ): JsonResponse {
        $topN = $request->query->getInt('topN', 5);

        // Validate topN parameter
        if ($topN < 1) {
            return $this->json([
                'status' => 'error',
                'message' => 'topN parameter must be a positive integer'
            ], 400);
        }

        $result = $questionTopicCountService->getQuestionCountPerTopic($subjectName, $topN);

        if ($result['status'] === 'OK') {
            return $this->json([
                'status' => 'success',
                'data' => [
                    'percentages' => $result['data'],
                    'description' => "Shows the top {$topN} topics by percentage of questions, with remaining topics grouped as 'Other'"
                ]
            ]);
        }

        return $this->json([
            'status' => 'error',
            'message' => $result['message'] ?? 'Failed to get question counts',
            'error' => $result['error'] ?? null
        ], 500);
    }
}