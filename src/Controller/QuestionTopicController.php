<?php

namespace App\Controller;

use App\Service\QuestionTopicService;
use App\Service\QuestionTopicUpdateService;
use App\Entity\Subject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/question-topics')]
class QuestionTopicController extends AbstractController
{
    public function __construct(
        private QuestionTopicService $questionTopicService,
        private QuestionTopicUpdateService $questionTopicUpdateService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/next', name: 'get_next_question_no_topic', methods: ['GET'])]
    public function getNextQuestionNoTopic(): JsonResponse
    {
        $question = $this->questionTopicService->getNextQuestionWithNoTopic();

        if (!$question) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'No questions found with null topic'
            ], 404);
        }

        $subject = $this->entityManager->getRepository(Subject::class)->find($question->getSubject()?->getId());
        $subjectTopics = $subject ? $subject->getTopics() : [];

        return $this->json([
            'status' => 'OK',
            'question' => [
                'id' => $question->getId(),
                'question' => $question->getQuestion(),
                'context' => $question->getContext(),
                'answer' => $question->getAnswer(),
                'subject_id' => $question->getSubject()?->getId(),
                'subject_topics' => $subjectTopics
            ]
        ]);
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