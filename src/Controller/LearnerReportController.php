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

    #[Route('/api/learner/{id}/subject-performance', name: 'learner_subject_performance', methods: ['GET'])]
    public function getSubjectPerformance(string $id): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $id]);
        if (!$learner) {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['followMeCode' => $id]);
            if (!$learner) {
                return new JsonResponse(['error' => 'Learner not found'], 404);
            }
        }

        $report = $this->reportService->getSubjectPerformance($learner);
        return new JsonResponse(['data' => $report]);
    }

    #[Route('/api/learner/{id}/daily-activity', name: 'learner_daily_activity', methods: ['GET'])]
    public function getDailyActivity(string $id, Request $request): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $id]);
        if (!$learner) {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['followMeCode' => $id]);
            if (!$learner) {
                return new JsonResponse(['error' => 'Learner not found'], 404);
            }
        }

        $subjectId = $request->query->get('subject_id');
        $report = $this->reportService->getDailyActivity($learner, $subjectId ? (int) $subjectId : null);
        return new JsonResponse(['data' => $report]);
    }

    #[Route('/api/learner/{id}/weekly-progress', name: 'learner_weekly_progress', methods: ['GET'])]
    public function getWeeklyProgress(string $id, Request $request): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $id]);
        if (!$learner) {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['followMeCode' => $id]);
            if (!$learner) {
                return new JsonResponse(['error' => 'Learner not found'], 404);
            }
        }

        $subjectId = $request->query->get('subject_id');
        $report = $this->reportService->getWeeklyProgress($learner, $subjectId ? (int) $subjectId : null);
        return new JsonResponse(['data' => $report]);
    }

    #[Route('/api/learner/{uid}/report', name: 'learner_report', methods: ['GET'])]
    public function getLearnerReport(string $uid, Request $request): JsonResponse
    {
        $subjectName = $request->query->get('subject');

        if (!$subjectName) {
            return new JsonResponse([
                'error' => 'Subject name is required'
            ], 400);
        }

        $report = $this->reportService->getLearnerReport($uid, $subjectName);

        return new JsonResponse([
            'uid' => $uid,
            'subject' => $subjectName,
            'report' => $report
        ]);
    }
}