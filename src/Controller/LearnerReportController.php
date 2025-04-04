<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\LearnerReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LearnerReportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LearnerReportService $reportService
    ) {
    }

    #[Route('/api/learner/{uid}/subject-performance', name: 'learner_subject_performance', methods: ['GET'])]
    public function getSubjectPerformance(string $uid): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $report = $this->reportService->getSubjectPerformance($learner);
        return new JsonResponse(['data' => $report]);
    }

    #[Route('/api/learner/{uid}/daily-activity', name: 'learner_daily_activity', methods: ['GET'])]
    public function getDailyActivity(string $uid): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $report = $this->reportService->getDailyActivity($learner);
        return new JsonResponse(['data' => $report]);
    }

    #[Route('/api/learner/{uid}/weekly-progress', name: 'learner_weekly_progress', methods: ['GET'])]
    public function getWeeklyProgress(string $uid): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $report = $this->reportService->getWeeklyProgress($learner);
        return new JsonResponse(['data' => $report]);
    }
} 