<?php

namespace App\Controller;

use App\Service\MathsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/maths')]
class MathsController extends AbstractController
{
    public function __construct(
        private readonly MathsService $mathsService
    ) {
    }

    #[Route('/topics-with-steps', name: 'get_topics_with_steps', methods: ['GET'])]
    public function getTopicsWithSteps(Request $request): JsonResponse
    {
        $learnerUid = $request->query->get('uid');
        $subjectName = $request->query->get('subject_name');

        if (empty($learnerUid)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        $topics = $this->mathsService->getTopicsWithSteps($learnerUid, $subjectName);

        return $this->json([
            'status' => 'OK',
            'topics' => $topics
        ]);
    }

    #[Route('/questions-with-steps', name: 'get_questions_with_steps', methods: ['GET'])]
    public function getQuestionsWithSteps(Request $request): JsonResponse
    {
        $topic = $request->query->get('topic');
        $grade = $request->query->get('grade');
        $subjectName = $request->query->get('subject_name');

        if (empty($topic)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Topic is required'
            ], 400);
        }

        if (empty($grade) || !is_numeric($grade)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Valid grade number is required'
            ], 400);
        }

        $questionIds = $this->mathsService->getQuestionIdsWithSteps($topic, (int) $grade, $subjectName);

        return $this->json([
            'status' => 'OK',
            'question_ids' => $questionIds
        ]);
    }
}