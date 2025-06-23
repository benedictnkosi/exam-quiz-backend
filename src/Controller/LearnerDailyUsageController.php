<?php

namespace App\Controller;

use App\Service\LearnerDailyUsageService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LearnerDailyUsageController extends AbstractController
{
    public function __construct(
        private readonly LearnerDailyUsageService $usageService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/api/learner/daily-usage', name: 'get_learner_daily_usage', methods: ['GET'])]
    public function getDailyUsage(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $learnerUid = $request->query->get('uid');
        if (empty($learnerUid)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        $result = $this->usageService->getDailyUsageByLearnerUid($learnerUid);
        return new JsonResponse($result);
    }

    #[Route('/api/learner/daily-usage/range', name: 'get_learner_daily_usage_range', methods: ['GET'])]
    public function getDailyUsageByRange(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $learnerUid = $request->query->get('uid');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (empty($learnerUid) || empty($startDate) || empty($endDate)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Learner UID, start date, and end date are required'
            ], 400);
        }

        try {
            $start = new \DateTimeImmutable($startDate);
            $end = new \DateTimeImmutable($endDate);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Invalid date format. Use YYYY-MM-DD'
            ], 400);
        }

        $result = $this->usageService->getDailyUsageByDateRange($learnerUid, $start, $end);
        return new JsonResponse($result);
    }

    #[Route('/api/learner/daily-usage/maths-practice', name: 'increment_learner_maths_practice', methods: ['POST'])]
    public function incrementMathsPractice(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $learnerUid = $request->query->get('uid');
        $questionId = $request->query->get('question_id');
        
        if (empty($learnerUid)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        if (empty($questionId)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Question ID is required'
            ], 400);
        }

        try {
            $learner = $this->usageService->getLearnerByUid($learnerUid);
            if (!$learner) {
                return new JsonResponse([
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ], 404);
            }

            $this->usageService->incrementMathsPracticeUsage($learner, (int) $questionId);

            return new JsonResponse([
                'status' => 'OK',
                'message' => 'Maths practice count incremented successfully'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error incrementing maths practice: ' . $e->getMessage());
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Error incrementing maths practice count'
            ], 500);
        }
    }

    #[Route('/api/learner/maths-practice-count', name: 'get_learner_maths_practice_count', methods: ['GET'])]
    public function getMathsPracticeCount(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $learnerUid = $request->query->get('uid');
        if (empty($learnerUid)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        $result = $this->usageService->getLearnerMathsPracticeCount($learnerUid);
        return new JsonResponse($result);
    }

    #[Route('/api/learner/maths-practice-question-ids', name: 'get_learner_maths_practice_question_ids', methods: ['GET'])]
    public function getMathsPracticeQuestionIds(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $learnerUid = $request->query->get('uid');
        if (empty($learnerUid)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Learner UID is required'
            ], 400);
        }

        $result = $this->usageService->getLearnerMathsPracticeQuestionIds($learnerUid);
        return new JsonResponse($result);
    }
}