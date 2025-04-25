<?php

namespace App\Controller;

use App\Service\QuestionTopicUpdateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/question-topics')]
class QuestionTopicController extends AbstractController
{
    public function __construct(
        private QuestionTopicUpdateService $questionTopicUpdateService
    ) {
    }

    #[Route('/update/{questionId}', name: 'update_question_topic', methods: ['PUT'])]
    public function updateQuestionTopic(int $questionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['topic'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Topic is required'
            ], 400);
        }

        $result = $this->questionTopicUpdateService->updateQuestionTopic($questionId, $data['topic']);

        return $this->json(
            $result,
            $result['status'] === 'OK' ? 200 : 400
        );
    }
}