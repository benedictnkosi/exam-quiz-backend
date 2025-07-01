<?php

namespace App\Controller;

use App\Service\AccountingQuestionGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api/accounting-questions/generate')]
class AccountingQuestionGeneratorController extends AbstractController
{
    public function __construct(
        private AccountingQuestionGeneratorService $generatorService
    ) {
    }

    /**
     * Generate a single accounting question using AI
     */
    #[Route('/single', name: 'generate_single_question', methods: ['POST'])]
    public function generateSingleQuestion(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'details' => json_last_error_msg()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['topic', 'level', 'questionType', 'mainTopic'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json([
                        'error' => "Missing required field: {$field}"
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $result = $this->generatorService->generateQuestion(
                $data['topic'],
                $data['level'],
                $data['questionType'],
                $data['mainTopic']
            );

            if ($result['success']) {
                return $this->json([
                    'message' => 'Question generated successfully',
                    'data' => $result['question'],
                    'token_usage' => $result['token_usage']
                ]);
            } else {
                return $this->json([
                    'error' => 'Failed to generate question',
                    'details' => $result['error']
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Question generation failed',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate multiple accounting questions using AI
     */
    #[Route('/multiple', name: 'generate_multiple_questions', methods: ['POST'])]
    public function generateMultipleQuestions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'details' => json_last_error_msg()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['topic', 'level', 'mainTopic'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json([
                        'error' => "Missing required field: {$field}"
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $count = $data['count'] ?? 5;
            $questionTypes = $data['questionTypes'] ?? ['tap-to-select', 'categorise', 'true-false', 'drag-to-sort', 'matching'];

            // Validate count
            if ($count < 1 || $count > 20) {
                return $this->json([
                    'error' => 'Count must be between 1 and 20'
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->generatorService->generateMultipleQuestions(
                $data['topic'],
                $data['level'],
                $data['mainTopic'],
                $count,
                $questionTypes
            );

            return $this->json([
                'message' => 'Questions generation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Questions generation failed',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available question types
     */
    #[Route('/types', name: 'get_question_types', methods: ['GET'])]
    public function getQuestionTypes(): JsonResponse
    {
        $types = [
            'tap-to-select' => 'Multiple choice with single correct answer',
            'categorise' => 'Drag and drop items into categories',
            'true-false' => 'True or false questions with explanations',
            'drag-to-sort' => 'Arrange items in correct order',
            'matching' => 'Match items to their correct categories',
            'step-flow' => 'Single calculation or problem-solving question',
            'multi-step' => 'Complex problem with multiple steps'
        ];

        return $this->json([
            'data' => $types
        ]);
    }

    /**
     * Get available topics and levels
     */
    #[Route('/metadata', name: 'get_generation_metadata', methods: ['GET'])]
    public function getMetadata(): JsonResponse
    {
        $topics = [
            'Financial Statements - Income Statement',
            'Financial Statements - Balance Sheet',
            'Financial Statements - Cash Flow Statement',
            'Accounting Equation',
            'Double Entry Bookkeeping',
            'Trial Balance',
            'Adjustments',
            'Closing Entries',
            'Inventory Valuation',
            'Depreciation',
            'Bad Debts',
            'Bank Reconciliation',
            'Petty Cash',
            'Payroll Accounting',
            'Cost Accounting',
            'Budgeting',
            'Financial Ratios',
            'Audit and Internal Control'
        ];

        $levels = [
            'Level 1: Basics' => 'Fundamental concepts and definitions (Grades 8-9)',
            'Level 2: Core Practice' => 'Application and calculations (Grades 10-11)',
            'Level 3: Advanced' => 'Complex scenarios and analysis (Grade 12)',
            'Level 4: Expert' => 'University preparation and advanced analysis (Grade 12+)'
        ];

        return $this->json([
            'data' => [
                'topics' => $topics,
                'levels' => $levels
            ]
        ]);
    }
} 