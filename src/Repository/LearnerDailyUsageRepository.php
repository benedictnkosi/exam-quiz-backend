<?php

namespace App\Repository;

use App\Entity\LearnerDailyUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerDailyUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerDailyUsage::class);
    }

    public function findByLearnerAndDate(int $learnerId, \DateTimeImmutable $date): ?LearnerDailyUsage
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.learner = :learnerId')
            ->andWhere('u.date >= :startDate')
            ->andWhere('u.date < :endDate')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('startDate', $date->setTime(0, 0, 0))
            ->setParameter('endDate', $date->setTime(0, 0, 0)->modify('+1 day'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByLearnerAndDateRange(int $learnerId, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.learner = :learnerId')
            ->andWhere('u.date >= :startDate')
            ->andWhere('u.date <= :endDate')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('startDate', $startDate->setTime(0, 0, 0))
            ->setParameter('endDate', $endDate->setTime(23, 59, 59))
            ->orderBy('u.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAverageLearnersPerDay(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): float
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.learner) as uniqueLearners, COUNT(DISTINCT u.date) as totalDays')
            ->where('u.date >= :startDate')
            ->andWhere('u.date <= :endDate')
            ->setParameter('startDate', $startDate->setTime(0, 0, 0))
            ->setParameter('endDate', $endDate->setTime(23, 59, 59))
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || $result['totalDays'] === 0) {
            return 0.0;
        }

        return $result['uniqueLearners'] / $result['totalDays'];
    }
}