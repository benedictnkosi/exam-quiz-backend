<?php

namespace App\Controller;

use App\Service\LearnerPracticeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learner-practice')]
class LearnerPracticeController extends AbstractController
{
    public function __construct(
        private readonly LearnerPracticeService $learnerPracticeService
    ) {
    }

    #[Route('/update/{subjectName}', name: 'update_practice_progress', methods: ['POST'])]
    public function updateProgress(string $subjectName, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['uid'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'User ID (uid) is required'
            ], 400);
        }

        if (!isset($data['progress'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Progress data is required'
            ], 400);
        }

        $result = $this->learnerPracticeService->updateProgress($data['uid'], $subjectName, $data['progress']);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    #[Route('/get/{subjectName}', name: 'get_practice_progress', methods: ['GET'])]
    public function getProgress(string $subjectName, Request $request): JsonResponse
    {
        $uid = $request->query->get('uid');
        $topic = $request->query->get('topic');

        if (!$uid) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'User ID (uid) is required'
            ], 400);
        }

        if (!$topic) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Topic is required'
            ], 400);
        }

        $result = $this->learnerPracticeService->getProgress($uid, $subjectName, $topic);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    #[Route('/get-all', name: 'get_all_practice_progress', methods: ['GET'])]
    public function getAllProgress(Request $request): JsonResponse
    {
        $uid = $request->query->get('uid');

        if (!$uid) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'User ID (uid) is required'
            ], 400);
        }

        $result = $this->learnerPracticeService->getAllProgress($uid);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}