<?php

namespace App\Controller;

use App\Service\StoryArcService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/story-arcs')]
class StoryArcController extends AbstractController
{
    public function __construct(
        private readonly StoryArcService $storyArcService
    ) {
    }

    #[Route('', name: 'story_arc_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $storyArc = $this->storyArcService->createStoryArc($data);
            return $this->json([
                'id' => $storyArc->getId(),
                'theme' => $storyArc->getTheme(),
                'goal' => $storyArc->getGoal(),
                'publish_date' => $storyArc->getPublishDate()->format('Y-m-d H:i:s'),
                'chapter_name' => $storyArc->getChapterName(),
                'outline' => $storyArc->getOutline(),
                'status' => $storyArc->getStatus(),
                'chapter_number' => $storyArc->getChapterNumber()
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('', name: 'story_arc_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $storyArcs = $this->storyArcService->getAllStoryArcs();
        $data = array_map(function ($storyArc) {
            $imageName = null;
            $chapterNumber = $storyArc->getChapterNumber();
            $imagePath = "public/images/chapter-{$chapterNumber}.png";

            if (file_exists($imagePath)) {
                $imageName = "chapter-{$chapterNumber}.png";
            }

            return [
                'id' => $storyArc->getId(),
                'theme' => $storyArc->getTheme(),
                'goal' => $storyArc->getGoal(),
                'publish_date' => $storyArc->getPublishDate()->format('Y-m-d H:i:s'),
                'chapter_name' => $storyArc->getChapterName(),
                'outline' => $storyArc->getOutline(),
                'status' => $storyArc->getStatus(),
                'chapter_number' => $storyArc->getChapterNumber(),
                'image' => $imageName
            ];
        }, $storyArcs);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'story_arc_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $storyArc = $this->storyArcService->getStoryArcById($id);
        if (!$storyArc) {
            return $this->json(['error' => 'Story arc not found'], 404);
        }

        return $this->json([
            'id' => $storyArc->getId(),
            'theme' => $storyArc->getTheme(),
            'goal' => $storyArc->getGoal(),
            'publish_date' => $storyArc->getPublishDate()->format('Y-m-d H:i:s'),
            'chapter_name' => $storyArc->getChapterName(),
            'outline' => $storyArc->getOutline(),
            'status' => $storyArc->getStatus(),
            'chapter_number' => $storyArc->getChapterNumber()
        ]);
    }

    #[Route('/{id}', name: 'story_arc_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $storyArc = $this->storyArcService->updateStoryArc($id, $data);

        if (!$storyArc) {
            return $this->json(['error' => 'Story arc not found'], 404);
        }

        return $this->json([
            'id' => $storyArc->getId(),
            'theme' => $storyArc->getTheme(),
            'goal' => $storyArc->getGoal(),
            'publish_date' => $storyArc->getPublishDate()->format('Y-m-d H:i:s'),
            'chapter_name' => $storyArc->getChapterName(),
            'outline' => $storyArc->getOutline(),
            'status' => $storyArc->getStatus(),
            'chapter_number' => $storyArc->getChapterNumber()
        ]);
    }

    #[Route('/{id}', name: 'story_arc_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $success = $this->storyArcService->deleteStoryArc($id);
        if (!$success) {
            return $this->json(['error' => 'Story arc not found'], 404);
        }

        return $this->json(null, 204);
    }

    #[Route('/bulk', name: 'story_arc_bulk_create', methods: ['POST'])]
    public function bulkCreate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid request format. Expected an array of story arcs.'], 400);
        }

        try {
            $result = $this->storyArcService->createBulkStoryArcs($data);

            if (!empty($result['errors'])) {
                return $this->json([
                    'message' => 'Some story arcs were created with errors',
                    'created' => $result['created'],
                    'errors' => $result['errors']
                ], 207); // 207 Multi-Status
            }

            return $this->json([
                'message' => 'All story arcs created successfully',
                'created' => $result['created']
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}