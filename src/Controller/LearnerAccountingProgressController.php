<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Entity\AccountingQuestion;
use App\Service\LearnerAccountingProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/learner-accounting-progress')]
class LearnerAccountingProgressController extends AbstractController
{
    public function __construct(
        private LearnerAccountingProgressService $progressService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Add a completed question for a learner
     */
    #[Route('/complete-question', name: 'learner_accounting_progress_complete_question', methods: ['POST'])]
    public function completeQuestion(Request $request): JsonResponse
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
            $requiredFields = ['learnerUid', 'questionId', 'isCorrect'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json([
                        'error' => 'Missing required field',
                        'field' => $field
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Find learner and question
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $data['learnerUid']]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'learnerUid' => $data['learnerUid']
                ], Response::HTTP_NOT_FOUND);
            }

            $question = $this->entityManager->getRepository(AccountingQuestion::class)->find($data['questionId']);
            if (!$question) {
                return $this->json([
                    'error' => 'Accounting question not found',
                    'questionId' => $data['questionId']
                ], Response::HTTP_NOT_FOUND);
            }

            // Add completed question
            $progress = $this->progressService->addCompletedQuestion(
                $learner,
                $question,
                $data['isCorrect'],
                $data['learnerAnswer'] ?? null,
                $data['correctAnswer'] ?? null,
                $data['timeSpent'] ?? null,
                $data['attempts'] ?? null,
                $data['notes'] ?? null
            );

            return $this->json([
                'message' => 'Question completion recorded successfully',
                'progressId' => $progress->getId(),
                'completedAt' => $progress->getCompletedAt()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to record question completion',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get completed topics for a learner
     */
    #[Route('/learner/{uid}/completed-topics', name: 'learner_accounting_progress_completed_topics', methods: ['GET'])]
    public function getCompletedTopics(string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $completedTopics = $this->progressService->getCompletedTopics($learner);
            
            return $this->json([
                'uid' => $uid,
                'completedTopics' => $completedTopics
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve completed topics',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get progress summary for a learner
     */
    #[Route('/learner/{uid}/summary', name: 'learner_accounting_progress_summary', methods: ['GET'])]
    public function getProgressSummary(string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $summary = $this->progressService->getProgressSummary($learner);
            
            return $this->json([
                'uid' => $uid,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve progress summary',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get progress statistics for a learner
     */
    #[Route('/learner/{uid}/stats', name: 'learner_accounting_progress_stats', methods: ['GET'])]
    public function getProgressStats(string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $stats = $this->progressService->getProgressStats($learner);
            
            return $this->json([
                'uid' => $uid,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve progress statistics',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get recent progress for a learner
     */
    #[Route('/learner/{uid}/recent', name: 'learner_accounting_progress_recent', methods: ['GET'])]
    public function getRecentProgress(Request $request, string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $limit = (int) $request->query->get('limit', 10);
            $recentProgress = $this->progressService->getRecentProgress($learner, $limit);
            
            return $this->json([
                'uid' => $uid,
                'recentProgress' => array_map(function($progress) {
                    return [
                        'id' => $progress->getId(),
                        'questionId' => $progress->getAccountingQuestion()->getQuestionId(),
                        'topic' => $progress->getAccountingTopic()->getSubTopic(),
                        'mainTopic' => $progress->getAccountingTopic()->getMainTopic(),
                        'isCorrect' => $progress->isCorrect(),
                        'completedAt' => $progress->getCompletedAt()->format('Y-m-d H:i:s'),
                        'timeSpent' => $progress->getTimeSpent()
                    ];
                }, $recentProgress)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve recent progress',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get progress by topic for a learner
     */
    #[Route('/learner/{uid}/topic/{topicId}', name: 'learner_accounting_progress_by_topic', methods: ['GET'])]
    public function getProgressByTopic(string $uid, int $topicId): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $progress = $this->progressService->getProgressByTopic($learner, $topicId);
            $completionStatus = $this->progressService->getTopicCompletionStatus($learner, $topicId);
            
            return $this->json([
                'uid' => $uid,
                'topicId' => $topicId,
                'completionStatus' => $completionStatus,
                'progress' => array_map(function($progress) {
                    return [
                        'id' => $progress->getId(),
                        'questionId' => $progress->getAccountingQuestion()->getQuestionId(),
                        'isCorrect' => $progress->isCorrect(),
                        'learnerAnswer' => $progress->getLearnerAnswer(),
                        'correctAnswer' => $progress->getCorrectAnswer(),
                        'timeSpent' => $progress->getTimeSpent(),
                        'attempts' => $progress->getAttempts(),
                        'completedAt' => $progress->getCompletedAt()->format('Y-m-d H:i:s')
                    ];
                }, $progress)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve topic progress',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if a learner has completed a specific question
     */
    #[Route('/learner/{uid}/question/{questionId}/completed', name: 'learner_accounting_progress_question_completed', methods: ['GET'])]
    public function checkQuestionCompleted(string $uid, int $questionId): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $isCompleted = $this->progressService->hasCompletedQuestion($learner, $questionId);
            
            return $this->json([
                'uid' => $uid,
                'questionId' => $questionId,
                'isCompleted' => $isCompleted
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to check question completion status',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get progress by date range for a learner
     */
    #[Route('/learner/{uid}/date-range', name: 'learner_accounting_progress_date_range', methods: ['GET'])]
    public function getProgressByDateRange(Request $request, string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $startDate = $request->query->get('startDate');
            $endDate = $request->query->get('endDate');

            if (!$startDate || !$endDate) {
                return $this->json([
                    'error' => 'startDate and endDate parameters are required (YYYY-MM-DD format)'
                ], Response::HTTP_BAD_REQUEST);
            }

            $startDateTime = new \DateTime($startDate);
            $endDateTime = new \DateTime($endDate);
            $endDateTime->setTime(23, 59, 59); // End of day

            $progress = $this->progressService->getProgressByDateRange($learner, $startDateTime, $endDateTime);
            
            return $this->json([
                'uid' => $uid,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'progress' => array_map(function($progress) {
                    return [
                        'id' => $progress->getId(),
                        'questionId' => $progress->getAccountingQuestion()->getQuestionId(),
                        'topic' => $progress->getAccountingTopic()->getSubTopic(),
                        'mainTopic' => $progress->getAccountingTopic()->getMainTopic(),
                        'isCorrect' => $progress->isCorrect(),
                        'completedAt' => $progress->getCompletedAt()->format('Y-m-d H:i:s'),
                        'timeSpent' => $progress->getTimeSpent()
                    ];
                }, $progress)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve date range progress',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get topic progress for a learner (showing completion percentage for each topic)
     */
    #[Route('/learner/{uid}/topic-progress', name: 'learner_accounting_progress_topic_progress', methods: ['GET'])]
    public function getTopicProgress(string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $topicProgress = $this->progressService->getTopicProgress($learner);
            
            return $this->json([
                'uid' => $uid,
                'topicProgress' => $topicProgress
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve topic progress',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get topic summary with statistics for each level
     */
    #[Route('/learner/{uid}/topic-summary', name: 'learner_accounting_progress_topic_summary', methods: ['GET'])]
    public function getTopicSummary(string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return $this->json([
                    'error' => 'Learner not found',
                    'uid' => $uid
                ], Response::HTTP_NOT_FOUND);
            }

            $topicSummary = $this->progressService->getTopicSummary($learner);
            
            return $this->json([
                'uid' => $uid,
                'topicSummary' => $topicSummary
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve topic summary',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 