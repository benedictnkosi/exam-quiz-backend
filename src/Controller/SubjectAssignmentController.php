<?php

namespace App\Controller;

use App\Service\SubjectAssignmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class SubjectAssignmentController extends AbstractController
{
    private SubjectAssignmentService $subjectAssignmentService;

    public function __construct(SubjectAssignmentService $subjectAssignmentService)
    {
        $this->subjectAssignmentService = $subjectAssignmentService;
    }

    #[Route('/subjects/{subjectId}/assign/{uid}', name: 'assign_subject_to_learner', methods: ['POST'])]
    public function assignSubjectToLearner(int $subjectId, string $uid): JsonResponse
    {
        try {
            $subject = $this->subjectAssignmentService->assignSubjectToLearner($subjectId, $uid);

            return $this->json([
                'status' => 'success',
                'message' => 'Subject assigned successfully',
                'data' => [
                    'subject_id' => $subject->getId(),
                    'subject_name' => $subject->getName(),
                    'learner_uid' => $uid
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }
}