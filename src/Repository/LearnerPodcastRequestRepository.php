<?php

namespace App\Repository;

use App\Entity\LearnerPodcastRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerPodcastRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerPodcastRequest::class);
    }

    public function countDailyRequests(int $learnerId, \DateTimeImmutable $date): int
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.learner = :learnerId')
            ->andWhere('p.requestedAt BETWEEN :start AND :end')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();
    }
}