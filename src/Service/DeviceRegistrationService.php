<?php

namespace App\Service;

use App\Entity\DeviceRegistration;
use App\Entity\Learner;
use App\Repository\DeviceRegistrationRepository;
use App\Repository\LearnerRepository;
use Doctrine\ORM\EntityManagerInterface;

class DeviceRegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeviceRegistrationRepository $deviceRegistrationRepository,
        private LearnerRepository $learnerRepository
    ) {}

    public function addDeviceId(string $deviceId, ?string $learnerUid = null): DeviceRegistration
    {
        // Check if device ID already exists
        $existingRegistration = $this->deviceRegistrationRepository->findByDeviceId($deviceId);
        
        if ($existingRegistration) {
            // Update learner UID if provided and different
            if ($learnerUid && $existingRegistration->getLearnerUid() !== $learnerUid) {
                $existingRegistration->setLearnerUid($learnerUid);
                $this->entityManager->flush();
            }
            return $existingRegistration;
        }

        $deviceRegistration = new DeviceRegistration();
        $deviceRegistration->setDeviceId($deviceId);
        $deviceRegistration->setLearnerUid($learnerUid);

        $this->entityManager->persist($deviceRegistration);
        // $this->entityManager->flush();

        return $deviceRegistration;
    }

    public function getRegistrationByDeviceId(string $deviceId): ?DeviceRegistration
    {
        return $this->deviceRegistrationRepository->findByDeviceId($deviceId);
    }

    public function getRegistrationByLearnerUid(string $learnerUid): ?DeviceRegistration
    {
        return $this->deviceRegistrationRepository->findByLearnerUid($learnerUid);
    }

    public function getRegistrationByDeviceIdAndLearnerUid(string $deviceId, string $learnerUid): ?DeviceRegistration
    {
        return $this->deviceRegistrationRepository->findByDeviceIdAndLearnerUid($deviceId, $learnerUid);
    }

    public function getRegistrationWithLearnerEmailByDeviceId(string $deviceId): ?array
    {
        $deviceRegistration = $this->deviceRegistrationRepository->findByDeviceId($deviceId);
        
        if (!$deviceRegistration) {
            return null;
        }

        $learnerEmail = null;
        if ($deviceRegistration->getLearnerUid()) {
            $learner = $this->learnerRepository->findOneBy(['uid' => $deviceRegistration->getLearnerUid()]);
            $learnerEmail = $learner?->getEmail();
        }

        return [
            'id' => $deviceRegistration->getId(),
            'deviceId' => $deviceRegistration->getDeviceId(),
            'learnerUid' => $deviceRegistration->getLearnerUid(),
            'learnerEmail' => $learnerEmail,
            'registrationDate' => $deviceRegistration->getRegistrationDate()->format('Y-m-d H:i:s')
        ];
    }

    public function getRegistrationWithLearnerEmailByLearnerUid(string $learnerUid): ?array
    {
        $deviceRegistration = $this->deviceRegistrationRepository->findByLearnerUid($learnerUid);
        
        if (!$deviceRegistration) {
            return null;
        }

        $learnerEmail = null;
        if ($deviceRegistration->getLearnerUid()) {
            $learner = $this->learnerRepository->findOneBy(['uid' => $deviceRegistration->getLearnerUid()]);
            $learnerEmail = $learner?->getEmail();
        }

        return [
            'id' => $deviceRegistration->getId(),
            'deviceId' => $deviceRegistration->getDeviceId(),
            'learnerUid' => $deviceRegistration->getLearnerUid(),
            'learnerEmail' => $learnerEmail,
            'registrationDate' => $deviceRegistration->getRegistrationDate()->format('Y-m-d H:i:s')
        ];
    }
} 