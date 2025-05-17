<?php

namespace App\Controller;

use App\Service\LearnerReadingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LearnerReadingController extends AbstractController
{
    public function __construct(
        private LearnerReadingService $learnerReadingService
    ) {
    }

    #[Route('/api/learner/next-chapter', name: 'get_next_chapter', methods: ['GET'])]
    public function getNextChapter(Request $request): JsonResponse
    {
        $learnerUid = $request->query->get('learnerUid');

        if (!$learnerUid) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        $result = $this->learnerReadingService->getNextChapter($learnerUid);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    #[Route('/api/learner/complete-chapter', name: 'complete_chapter', methods: ['POST'])]
    public function completeChapter(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['learnerUid']) || !isset($data['chapterId'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID and Chapter ID are required'
            ], 400);
        }

        $duration = $data['duration'] ?? 0;
        $score = $data['score'] ?? 0;

        $result = $this->learnerReadingService->markChapterComplete(
            $data['learnerUid'],
            $data['chapterId'],
            $duration,
            $score
        );

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    #[Route('/api/learner/past-chapters', name: 'get_past_chapters', methods: ['GET'])]
    public function getPastChapters(Request $request): JsonResponse
    {
        $learnerUid = $request->query->get('learnerUid');

        if (!$learnerUid) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        $result = $this->learnerReadingService->getPastChapters($learnerUid);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    #[Route('/api/learner/chapter/{chapterId}', name: 'get_chapter_by_id', methods: ['GET'])]
    public function getChapterById(Request $request, int $chapterId): JsonResponse
    {
        $learnerUid = $request->query->get('learnerUid');

        if (!$learnerUid) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID is required. Example: /api/learner/chapter/1?learnerUid=your_learner_uid'
            ], 400);
        }

        $result = $this->learnerReadingService->getChapterById($learnerUid, $chapterId);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    #[Route('/api/learner/reading-stats', name: 'get_learner_reading_stats', methods: ['GET'])]
    public function getLearnerReadingStats(Request $request): JsonResponse
    {
        $learnerUid = $request->query->get('learnerUid');

        if (!$learnerUid) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Learner UID is required. Example: /api/learner/reading-stats?learnerUid=your_learner_uid'
            ], 400);
        }

        $result = $this->learnerReadingService->getLearnerReadingStats($learnerUid);

        if ($result['status'] === 'NOK') {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}