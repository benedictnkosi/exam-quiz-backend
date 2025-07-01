<?php

namespace App\Repository;

use App\Entity\DeviceRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DeviceRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceRegistration::class);
    }

    public function findByDeviceId(string $deviceId): ?DeviceRegistration
    {
        return $this->findOneBy(['deviceId' => $deviceId]);
    }

    public function findByLearnerUid(string $learnerUid): ?DeviceRegistration
    {
        return $this->findOneBy(['learnerUid' => $learnerUid]);
    }

    public function findByDeviceIdAndLearnerUid(string $deviceId, string $learnerUid): ?DeviceRegistration
    {
        return $this->findOneBy([
            'deviceId' => $deviceId,
            'learnerUid' => $learnerUid
        ]);
    }
} 