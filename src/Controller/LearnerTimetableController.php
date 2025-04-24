<?php

namespace App\Controller;

use App\Service\LearnerTimetableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

#[Route('/api/learner')]
class LearnerTimetableController extends AbstractController
{
    public function __construct(
        private LearnerTimetableService $learnerTimetableService,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('/{uid}/timetable', name: 'learner_timetable_update', methods: ['PUT'])]
    public function updateTimetable(string $uid, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['timetable']) || !is_array($data['timetable'])) {
            return new JsonResponse(['error' => 'Invalid timetable data'], 400);
        }

        $this->learnerTimetableService->updateTimetable($uid, $data['timetable']);

        return new JsonResponse([
            'status' => 'OK',
            'message' => 'Timetable updated successfully'
        ], 200);
    }

    #[Route('/{uid}/events', name: 'learner_events_update', methods: ['PUT'])]
    public function updateEvents(string $uid, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['events']) || !is_array($data['events'])) {
            return new JsonResponse(['error' => 'Invalid events data'], 400);
        }

        $this->learnerTimetableService->updateEvents($uid, $data['events']);

        return new JsonResponse([
            'status' => 'OK',
            'message' => 'Events updated successfully'
        ], 200);
    }
}