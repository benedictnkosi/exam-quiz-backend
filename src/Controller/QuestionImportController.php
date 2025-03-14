<?php

namespace App\Controller;

use App\Service\QuestionImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class QuestionImportController extends AbstractController
{
    public function __construct(
        private QuestionImportService $questionImportService
    ) {
    }

    #[Route('/questions/import', name: 'questions_import', methods: ['POST'])]
    public function importQuestions(Request $request): JsonResponse
    {
        try {
            $jsonContent = $request->getContent();

            if (empty($jsonContent)) {
                return $this->json([
                    'error' => 'No JSON content provided'
                ], 400);
            }

            $result = $this->questionImportService->importFromJson($jsonContent);

            if (!empty($result['errors'])) {
                return $this->json([
                    'message' => 'Some questions failed to import',
                    'imported_count' => count($result['imported']),
                    'error_count' => count($result['errors']),
                    'errors' => $result['errors']
                ], 207);
            }

            return $this->json([
                'message' => 'Questions imported successfully',
                'imported_count' => count($result['imported'])
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to import questions: ' . $e->getMessage()
            ], 500);
        }
    }
}