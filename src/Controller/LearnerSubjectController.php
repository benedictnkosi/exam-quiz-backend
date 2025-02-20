<?php

namespace App\Controller;

use App\Service\LearnerSubjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learner-subjects')]
class LearnerSubjectController extends AbstractController
{
    public function __construct(
        private LearnerSubjectService $learnerSubjectService
    ) {}

    #[Route('/add-all', methods: ['POST'])]
    public function addAllSubjects(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['uid']) || !isset($data['grade'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'UID and grade are required'
            ], 400);
        }

        $result = $this->learnerSubjectService->addAllSubjectsForGrade(
            $data['uid'],
            $data['grade']
        );

        return $this->json($result, 
            $result['status'] === 'OK' ? 200 : 400
        );
    }
} 