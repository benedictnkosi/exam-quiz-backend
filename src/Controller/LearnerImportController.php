<?php

namespace App\Controller;

use App\Service\LearnerImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learners')]
class LearnerImportController extends AbstractController
{
    private LearnerImportService $learnerImportService;

    public function __construct(LearnerImportService $learnerImportService)
    {
        $this->learnerImportService = $learnerImportService;
    }

    /**
     * Import learners from JSON file
     */
    #[Route('/import', name: 'api_learners_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        // Check if the request has a file
        if ($request->files->has('file')) {
            $file = $request->files->get('file');
            $result = $this->learnerImportService->importFromJson($file);
        } else {
            // Check if the request has JSON content
            $content = $request->getContent();
            if (empty($content)) {
                return $this->json([
                    'success' => false,
                    'message' => 'No file or JSON data provided',
                ], 400);
            }

            $result = $this->learnerImportService->importFromJson($content);
        }

        // Return response based on import result
        if (empty($result['errors'])) {
            return $this->json([
                'success' => true,
                'message' => sprintf('Successfully imported %d learners', $result['success']),
                'count' => $result['success'],
            ]);
        } else {
            return $this->json([
                'success' => $result['success'] > 0,
                'message' => sprintf('Imported %d learners with %d errors', $result['success'], count($result['errors'])),
                'count' => $result['success'],
                'errors' => $result['errors'],
            ], $result['success'] > 0 ? 200 : 400);
        }
    }
}