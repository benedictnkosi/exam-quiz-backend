<?php

namespace App\Controller;

use App\Service\SubjectQuestionCountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class SubjectQuestionCountController extends AbstractController
{
    private SubjectQuestionCountService $subjectQuestionCountService;

    public function __construct(SubjectQuestionCountService $subjectQuestionCountService)
    {
        $this->subjectQuestionCountService = $subjectQuestionCountService;
    }

    #[Route('/subjects/questions/count/{term}', name: 'subjects_questions_count', methods: ['GET'])]
    public function getQuestionCountsByTerm(int $term): JsonResponse
    {
        $questionCounts = $this->subjectQuestionCountService->getQuestionCountsByTerm($term);

        return $this->json([
            'status' => 'success',
            'data' => $questionCounts
        ]);
    }
}