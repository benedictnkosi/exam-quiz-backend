<?php

namespace App\Controller;

use App\Service\ChapterQuizService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChapterQuizController extends AbstractController
{
    public function __construct(
        private ChapterQuizService $chapterQuizService
    ) {
    }

    #[Route('/api/chapter/{chapterId}/quiz', name: 'get_chapter_quiz', methods: ['GET'])]
    public function getChapterQuiz(int $chapterId): JsonResponse
    {
        $result = $this->chapterQuizService->getChapterQuiz($chapterId);

        return new JsonResponse(
            $result,
            $result['status'] === 'OK' ? 200 : 404
        );
    }
}