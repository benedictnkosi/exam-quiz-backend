<?php

namespace App\Controller;

use App\Service\LearnerCompletedChapterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learner-completed-chapters')]
class LearnerCompletedChapterController extends AbstractController
{
    public function __construct(
        private LearnerCompletedChapterService $learnerCompletedChapterService
    ) {}

    #[Route('', methods: ['POST'])]
    public function addCompletedChapter(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validate required fields
        if (!isset($data['learnerUid']) || empty($data['learnerUid'])) {
            return $this->json(['error' => 'Learner UID is required'], 400);
        }

        if (!isset($data['chapterName']) || empty($data['chapterName'])) {
            return $this->json(['error' => 'Chapter name is required'], 400);
        }

        if (!isset($data['bookTitle']) || empty($data['bookTitle'])) {
            return $this->json(['error' => 'Book title is required'], 400);
        }

        $duration = $data['duration'] ?? null;
        $score = $data['score'] ?? null;
        $profileId = $data['profileId'] ?? null;

        try {
            $completedChapter = $this->learnerCompletedChapterService->addCompletedChapter(
                $data['learnerUid'],
                $data['chapterName'],
                $data['bookTitle'],
                $duration,
                $score,
                $profileId
            );

            return $this->json([
                'id' => $completedChapter->getId(),
                'learnerUid' => $completedChapter->getLearnerUid(),
                'profileId' => $completedChapter->getProfileId(),
                'chapterName' => $completedChapter->getChapterName(),
                'bookTitle' => $completedChapter->getBookTitle(),
                'completedAt' => $completedChapter->getCompletedAt()->format('Y-m-d H:i:s'),
                'duration' => $completedChapter->getDuration(),
                'score' => $completedChapter->getScore(),
                'message' => 'Chapter completed successfully'
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to add completed chapter: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/learner/{learnerUid}', methods: ['GET'])]
    public function getCompletedChaptersByLearner(string $learnerUid): JsonResponse
    {
        try {
            $completedChapters = $this->learnerCompletedChapterService->getCompletedChaptersByLearner($learnerUid);
            
            $response = [];
            foreach ($completedChapters as $chapter) {
                $response[] = [
                    'id' => $chapter->getId(),
                    'learnerUid' => $chapter->getLearnerUid(),
                    'profileId' => $chapter->getProfileId(),
                    'chapterName' => $chapter->getChapterName(),
                    'bookTitle' => $chapter->getBookTitle(),
                    'completedAt' => $chapter->getCompletedAt()->format('Y-m-d H:i:s'),
                    'duration' => $chapter->getDuration(),
                    'score' => $chapter->getScore()
                ];
            }

            return $this->json([
                'learnerUid' => $learnerUid,
                'completedChapters' => $response,
                'totalCount' => count($response)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve completed chapters: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/learner/{learnerUid}/book/{bookTitle}', methods: ['GET'])]
    public function getCompletedChaptersByLearnerAndBook(string $learnerUid, string $bookTitle): JsonResponse
    {
        try {
            $completedChapters = $this->learnerCompletedChapterService->getCompletedChaptersByLearnerAndBook($learnerUid, $bookTitle);
            
            $response = [];
            foreach ($completedChapters as $chapter) {
                $response[] = [
                    'id' => $chapter->getId(),
                    'learnerUid' => $chapter->getLearnerUid(),
                    'profileId' => $chapter->getProfileId(),
                    'chapterName' => $chapter->getChapterName(),
                    'bookTitle' => $chapter->getBookTitle(),
                    'completedAt' => $chapter->getCompletedAt()->format('Y-m-d H:i:s'),
                    'duration' => $chapter->getDuration(),
                    'score' => $chapter->getScore()
                ];
            }

            return $this->json([
                'learnerUid' => $learnerUid,
                'bookTitle' => $bookTitle,
                'completedChapters' => $response,
                'totalCount' => count($response)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve completed chapters: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/learner/{learnerUid}/count', methods: ['GET'])]
    public function getCompletedChaptersCountByLearner(string $learnerUid): JsonResponse
    {
        try {
            $count = $this->learnerCompletedChapterService->getCompletedChaptersCountByLearner($learnerUid);

            return $this->json([
                'learnerUid' => $learnerUid,
                'totalCompletedChapters' => $count
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve completed chapters count: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/learner/{learnerUid}/chapter/{chapterName}/check', methods: ['GET'])]
    public function checkChapterCompletion(string $learnerUid, string $chapterName): JsonResponse
    {
        try {
            $isCompleted = $this->learnerCompletedChapterService->isChapterCompleted($learnerUid, $chapterName);

            return $this->json([
                'learnerUid' => $learnerUid,
                'chapterName' => $chapterName,
                'isCompleted' => $isCompleted
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to check chapter completion: ' . $e->getMessage()], 500);
        }
    }
} 