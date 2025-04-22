<?php

namespace App\Controller;

use App\Service\LearnMzansiApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class QuestionController extends AbstractController
{
    public function __construct(
        private LearnMzansiApi $learnMzansiApi
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
}