<?php

namespace App\Controller;

use App\Entity\Unit;
use App\Service\LessonManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\LanguageQuestions;
use App\Entity\Word;

#[Route('/api/lessons')]
class LessonManagementController extends AbstractController
{
    private LessonManagementService $lessonService;
    private EntityManagerInterface $em;

    public function __construct(LessonManagementService $lessonService, EntityManagerInterface $em)
    {
        $this->lessonService = $lessonService;
        $this->em = $em;
    }

    #[Route('', name: 'add_lesson', methods: ['POST'])]
    public function addLesson(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['unitId'])) {
            return $this->json(['error' => 'unitId is required.'], 400);
        }
        $unit = $this->em->getRepository(Unit::class)->find($data['unitId']);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found.'], 404);
        }
        $lesson = $this->lessonService->addLesson($data, $unit);
        return $this->json([
            'id' => $lesson->getId(),
            'title' => $lesson->getTitle(),
            'lessonOrder' => $lesson->getLessonOrder(),
            'unitId' => $unit->getId(),
        ]);
    }

    #[Route('', name: 'get_lessons', methods: ['GET'])]
    public function getLessons(Request $request): JsonResponse
    {
        $language = $request->query->get('language');
        $lessons = $this->lessonService->getLessons();
        $result = array_map(function ($lesson) use ($language) {
            $unit = $lesson->getUnit();
            $hasLanguage = true;

            if ($language) {
                $availableLanguages = $unit->getAvailableLanguages() ?? [];
                $hasLanguage = in_array($language, $availableLanguages);
            }

            return [
                'id' => $lesson->getId(),
                'title' => $lesson->getTitle(),
                'lessonOrder' => $lesson->getLessonOrder(),
                'unitId' => $unit->getId(),
                'unitOrder' => $unit->getUnitOrder(),
                'unitName' => $unit->getTitle(),
                'unitDescription' => $unit->getDescription(),
                'hasLanguage' => $hasLanguage
            ];
        }, $lessons);

        // Filter out lessons without the requested language if language is specified
        if ($language) {
            $result = array_filter($result, function ($lesson) {
                return $lesson['hasLanguage'];
            });
        }

        return $this->json($result);
    }

    #[Route('/{id}', name: 'get_lesson', methods: ['GET'])]
    public function getLesson(int $id): JsonResponse
    {
        $lesson = $this->lessonService->getLesson($id);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], 404);
        }
        return $this->json([
            'id' => $lesson->getId(),
            'title' => $lesson->getTitle(),
            'lessonOrder' => $lesson->getLessonOrder(),
            'unitId' => $lesson->getUnit()->getId(),
        ]);
    }

    #[Route('/{id}', name: 'update_lesson', methods: ['PUT'])]
    public function updateLesson(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $lesson = $this->lessonService->updateLesson($id, $data);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], 404);
        }
        return $this->json([
            'id' => $lesson->getId(),
            'title' => $lesson->getTitle(),
            'lessonOrder' => $lesson->getLessonOrder(),
            'unitId' => $lesson->getUnit()->getId(),
        ]);
    }

    #[Route('/{id}', name: 'delete_lesson', methods: ['DELETE'])]
    public function deleteLesson(int $id): JsonResponse
    {
        $success = $this->lessonService->deleteLesson($id);
        if (!$success) {
            return $this->json(['error' => 'Lesson not found.'], 404);
        }
        return $this->json(['success' => true]);
    }

    #[Route('/unit/{unitId}', name: 'get_lessons_by_unit', methods: ['GET'])]
    public function getLessonsByUnit(int $unitId): JsonResponse
    {
        $lessons = $this->lessonService->getLessons();
        $result = array_values(array_filter($lessons, function ($lesson) use ($unitId) {
            return $lesson->getUnit()->getId() === $unitId;
        }));

        $response = array_map(function ($lesson) {
            return [
                'id' => $lesson->getId(),
                'title' => $lesson->getTitle(),
                'lessonOrder' => $lesson->getLessonOrder(),
                'unitId' => $lesson->getUnit()->getId()
            ];
        }, $result);

        return $this->json($response);
    }
}