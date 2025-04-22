<?php

namespace App\Controller;

use App\Service\LearnMzansiApi;
use App\Service\QuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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

    #[Route('/api/questions/first-unposted', name: 'get_first_unposted_question', methods: ['GET'])]
    public function getFirstUnpostedQuestion(): JsonResponse
    {
        $question = $this->questionService->getFirstUnpostedQuestion();

        if (!$question) {
            return new JsonResponse([
                'message' => 'No unposted questions found'
            ], 404);
        }

        return new JsonResponse([
            'id' => $question->getId(),
            'question' => $question->getQuestion(),
            'type' => $question->getType(),
            'context' => $question->getContext(),
            'answer' => $question->getAnswer(),
            'options' => $question->getOptions(),
            'term' => $question->getTerm(),
            'year' => $question->getYear(),
            'subject' => $question->getSubject()?->getName(),
            'topic' => $question->getTopic(),
            'curriculum' => $question->getCurriculum(),
            'imagePath' => $question->getImagePath(),
            'answerImage' => $question->getAnswerImage(),
            'explanation' => $question->getExplanation(),
            'aiExplanation' => $question->getAiExplanation(),
            'answerSheet' => $question->getAnswerSheet(),
            'otherContextImages' => $question->getOtherContextImages(),
            'relatedQuestionIds' => $question->getRelatedQuestionIds(),
            'status' => $question->getStatus(),
            'created' => $question->getCreated()?->format('Y-m-d H:i:s'),
            'updated' => $question->getUpdated()?->format('Y-m-d H:i:s'),
            'reviewedAt' => $question->getReviewedAt()?->format('Y-m-d H:i:s'),
            'comment' => $question->getComment(),
            'capturer' => $question->getCapturer()?->getId(),
            'reviewer' => $question->getReviewer()?->getId(),
            'posted' => $question->isPosted(),
            'active' => $question->isActive(),
            'higherGrade' => $question->getHigherGrade()
        ]);
    }
}