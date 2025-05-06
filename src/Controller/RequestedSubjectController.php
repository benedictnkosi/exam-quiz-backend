<?php

namespace App\Controller;

use App\Service\RequestedSubjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RequestedSubjectController extends AbstractController
{
    public function __construct(
        private RequestedSubjectService $requestedSubjectService
    ) {
    }

    #[Route('/api/request-subject', name: 'request_subject', methods: ['POST'])]
    public function requestSubject(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['learnerUid']) || !isset($data['subjectName'])) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Missing required parameters'
            ], 400);
        }

        $result = $this->requestedSubjectService->requestSubject(
            $data['learnerUid'],
            $data['subjectName']
        );

        return new JsonResponse($result);
    }

    #[Route('/api/subject-request-report', name: 'subject_request_report', methods: ['GET'])]
    public function getSubjectRequestReport(): JsonResponse
    {
        $result = $this->requestedSubjectService->getSubjectRequestReport();
        return new JsonResponse($result);
    }

    #[Route('/api/subjects/request-counts', name: 'app_subjects_request_counts', methods: ['GET'])]
    public function getSubjectRequestCounts(): JsonResponse
    {
        $counts = $this->requestedSubjectService->getSubjectRequestCounts();

        return $this->json([
            'success' => true,
            'data' => $counts
        ]);
    }
}