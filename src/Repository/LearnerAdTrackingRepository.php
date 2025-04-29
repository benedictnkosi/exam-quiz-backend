<?php

namespace App\Repository;

use App\Entity\LearnerAdTracking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerAdTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerAdTracking::class);
    }

    public function findByLearnerId(int $learnerId): ?LearnerAdTracking
    {
        return $this->findOneBy(['learner' => $learnerId]);
    }
}