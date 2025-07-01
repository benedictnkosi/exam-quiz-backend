<?php

namespace App\Controller;

use App\Service\AccountingQuestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api/accounting-questions')]
class AccountingQuestionController extends AbstractController
{
    public function __construct(
        private AccountingQuestionService $accountingQuestionService
    ) {
    }

    /**
     * Import accounting questions from JSON
     */
    #[Route('/import', name: 'accounting_questions_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'error' => 'Invalid JSON format',
                    'details' => json_last_error_msg()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check for overwrite parameter
            $overwrite = $request->query->getBoolean('overwrite', false);

            $result = $this->accountingQuestionService->importFromJson($data, $overwrite);

            return $this->json([
                'message' => 'Import completed',
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'skipped_details' => $result['skipped_details'],
                'updated_details' => $result['updated_details']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Import failed',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all questions grouped by topic and level
     */
    #[Route('', name: 'accounting_questions_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $questions = $this->accountingQuestionService->getQuestionsByTopicAndLevel();
            
            return $this->json([
                'data' => $questions
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve questions',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available main topics
     */
    #[Route('/main-topics', name: 'accounting_questions_main_topics', methods: ['GET'])]
    public function getMainTopics(): JsonResponse
    {
        try {
            $mainTopics = $this->accountingQuestionService->getAvailableMainTopics();
            
            return $this->json([
                'data' => $mainTopics
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve main topics',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get subtopics by main topic with their levels
     */
    #[Route('/main-topic/{mainTopic}/subtopics', name: 'accounting_questions_subtopics_by_main_topic', methods: ['GET'])]
    public function getSubtopicsByMainTopic(string $mainTopic): JsonResponse
    {
        try {
            $subtopics = $this->accountingQuestionService->getSubtopicsByMainTopic($mainTopic);
            
            return $this->json([
                'main_topic' => $mainTopic,
                'subtopics' => $subtopics
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve subtopics for main topic',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get questions by topic and level
     */
    #[Route('/topic/{topic}/level/{level}', name: 'accounting_questions_by_topic_and_level', methods: ['GET'])]
    public function getByTopicAndLevel(string $topic, string $level): JsonResponse
    {
        try {
            $questions = $this->accountingQuestionService->getQuestionsByTopicAndLevel();
            
            $result = [];
            // Search through the nested structure to find questions for the specific topic and level
            foreach ($questions as $mainTopic => $subtopics) {
                foreach ($subtopics as $subtopic => $levels) {
                    if ($subtopic === $topic && isset($levels[$level])) {
                        $result = $levels[$level];
                        break 2;
                    }
                }
            }
            
            return $this->json([
                'topic' => $topic,
                'level' => $level,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve questions for topic and level',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 