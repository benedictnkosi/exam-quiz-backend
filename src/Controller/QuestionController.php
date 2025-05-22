<?php

namespace App\Controller;

use App\Service\LearnMzansiApi;
use App\Service\QuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/questions')]
class QuestionController extends AbstractController
{
    public function __construct(
        private LearnMzansiApi $learnMzansiApi,
        private readonly QuestionService $questionService
    ) {
    }

    /**
     * Update the topic of a question
     * 
     * Request body should contain:
     * {
     *     "uid": "user_id",          // Required: User ID of admin
     *     "question_id": 123,        // Required: ID of question to update
     *     "topic": "New Topic"       // Required: New topic value
     * }
     */
    #[Route('/api/question/topic', name: 'update_question_topic', methods: ['POST'])]
    public function updateQuestionTopic(Request $request): JsonResponse
    {
        $result = $this->learnMzansiApi->updateQuestionTopic($request);
        return new JsonResponse($result);
    }

    #[Route('/{questionId}/practice-status', name: 'update_question_practice_status', methods: ['PUT'])]
    public function updatePracticeStatus(int $questionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Status is required'
            ], 400);
        }

        if (!isset($data['uid'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'User ID (uid) is required'
            ], 400);
        }

        $result = $this->questionService->updatePracticeStatus($questionId, $data['status'], $data['uid']);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}