<?php

namespace App\Controller;

use App\Service\LearnerEventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learner')]
class LearnerEventController extends AbstractController
{
    public function __construct(
        private LearnerEventService $learnerEventService
    ) {
    }

    #[Route('/{uid}/upcoming-events', name: 'learner_upcoming_events', methods: ['GET'])]
    public function getUpcomingEvents(string $uid): JsonResponse
    {
        $result = $this->learnerEventService->getUpcomingEvents($uid);

        if ($result['status'] === 'NOK') {
            return new JsonResponse($result, 404);
        }

        return new JsonResponse($result);
    }

    #[Route('/{id}/today-events', name: 'learner_today_events', methods: ['GET'])]
    public function getTodaysEvents(string $id): JsonResponse
    {
        $result = $this->learnerEventService->getTodaysEvents($id);

        if ($result['status'] === 'NOK') {
            return new JsonResponse($result, 404);
        }

        return new JsonResponse($result);
    }
}