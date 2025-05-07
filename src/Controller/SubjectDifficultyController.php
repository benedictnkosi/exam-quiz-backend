<?php

namespace App\Controller;

use App\Service\SubjectDifficultyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class SubjectDifficultyController extends AbstractController
{
    public function __construct(
        private readonly SubjectDifficultyService $subjectDifficultyService
    ) {
    }

    #[Route('/api/subject-difficulty/{grade}', name: 'app_subject_difficulty', methods: ['GET'])]
    public function getSubjectDifficulty(int $grade): JsonResponse
    {
        $result = $this->subjectDifficultyService->getSubjectDifficulty($grade);
        return $this->json($result);
    }
}