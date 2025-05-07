<?php

namespace App\Controller;

use App\Service\SubjectPopularityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SubjectPopularityController extends AbstractController
{
    public function __construct(
        private readonly SubjectPopularityService $subjectPopularityService
    ) {
    }

    #[Route('/api/subject-popularity/{grade}', name: 'app_subject_popularity', methods: ['GET'])]
    public function getSubjectPopularity(int $grade): JsonResponse
    {
        $result = $this->subjectPopularityService->getSubjectPopularity($grade);
        return $this->json($result);
    }
}