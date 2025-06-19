<?php

namespace App\Controller;

use App\Service\MathsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\LearnerService;

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

        if (empty($subjectName)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Subject name is required'
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

        if (empty($subjectName)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Subject name is required'
            ], 400);
        }

        $questionIds = $this->mathsService->getQuestionIdsWithSteps($topic, (int) $grade, $subjectName);

        return $this->json([
            'status' => 'OK',
            'question_ids' => $questionIds
        ]);
    }

    #[Route('/questions-with-steps-by-topic', name: 'get_questions_with_steps_by_topic', methods: ['GET'])]
    public function getQuestionsWithStepsByTopic(Request $request): JsonResponse
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

        if (empty($subjectName)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Subject name is required'
            ], 400);
        }

        $questionIds = $this->mathsService->getQuestionIdsWithStepsByTopic($topic, (int) $grade, $subjectName);

        return $this->json([
            'status' => 'OK',
            'question_ids' => $questionIds
        ]);
    }

    #[Route('/topics-subtopics-with-steps', name: 'get_topics_subtopics_with_steps', methods: ['GET'])]
    public function getTopicsAndSubtopicsWithSteps(Request $request): JsonResponse
    {
        $grade = $request->query->get('grade');
        $subjectName = $request->query->get('subject_name', 'Mathematics P1');

        if (empty($grade) || !is_numeric($grade)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Valid grade number is required'
            ], 400);
        }

        $topics = $this->mathsService->getTopicsAndSubtopicsWithSteps((int) $grade, $subjectName);

        return $this->json([
            'status' => 'OK',
            'topics' => $topics
        ]);
    }

    #[Route('/update-maths-points', name: 'update_maths_points', methods: ['POST'])]
    public function updateMathsPoints(Request $request, EntityManagerInterface $em, LearnerService $learnerService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $learnerUid = $data['uid'] ?? $request->request->get('uid');
        $points = $data['points'] ?? $request->request->get('points');

        if (empty($learnerUid)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        if (empty($points) || !is_numeric($points) || $points <= 0) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Valid points value is required'
            ], 400);
        }

        $learner = $em->getRepository(\App\Entity\Learner::class)->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner not found'
            ], 404);
        }

        $learnerService->incrementMathsPoints($learner, (int) $points);

        return $this->json([
            'status' => 'OK',
            'message' => 'Maths points updated successfully',
            'maths_points' => $learner->getMathsPoints()
        ]);
    }
}