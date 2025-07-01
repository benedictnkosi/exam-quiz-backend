<?php

namespace App\Controller;

use App\Service\DeviceRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/device-registration')]
class DeviceRegistrationController extends AbstractController
{
    public function __construct(
        private DeviceRegistrationService $deviceRegistrationService
    ) {}

    #[Route('', methods: ['POST'])]
    public function addDeviceId(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['deviceId']) || empty($data['deviceId'])) {
            return $this->json(['error' => 'Device ID is required'], 400);
        }

        $learnerUid = $data['learnerUid'] ?? null;
        $deviceRegistration = $this->deviceRegistrationService->addDeviceId($data['deviceId'], $learnerUid);

        return $this->json([
            'id' => $deviceRegistration->getId(),
            'deviceId' => $deviceRegistration->getDeviceId(),
            'learnerUid' => $deviceRegistration->getLearnerUid(),
            'registrationDate' => $deviceRegistration->getRegistrationDate()->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/device/{deviceId}', methods: ['GET'])]
    public function getRegistrationByDeviceId(string $deviceId): JsonResponse
    {
        $deviceRegistration = $this->deviceRegistrationService->getRegistrationWithLearnerEmailByDeviceId($deviceId);

        if (!$deviceRegistration) {
            return $this->json(['error' => 'Device registration not found'], 404);
        }

        return $this->json($deviceRegistration);
    }

    #[Route('/learner/{learnerUid}', methods: ['GET'])]
    public function getRegistrationByLearnerUid(string $learnerUid): JsonResponse
    {
        $deviceRegistration = $this->deviceRegistrationService->getRegistrationWithLearnerEmailByLearnerUid($learnerUid);

        if (!$deviceRegistration) {
            return $this->json(['error' => 'Device registration not found'], 404);
        }

        return $this->json($deviceRegistration);
    }
} 