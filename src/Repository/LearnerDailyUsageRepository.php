<?php

namespace App\Repository;

use App\Entity\LearnerDailyUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;
use App\Entity\Result;

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

    public function getUniqueLearnersPerDay(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('u')
            ->select('SUBSTRING(u.date, 1, 10) as date', 'COUNT(DISTINCT u.learner) as unique_learners')
            ->where('u.date >= :startDate')
            ->andWhere('u.date <= :endDate')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('startDate', $startDate->setTime(0, 0, 0))
            ->setParameter('endDate', $endDate->setTime(23, 59, 59))
            ->getQuery()
            ->getResult();
    }

    public function findFreeUsersWithHighActivity(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('SUBSTRING(r.created, 1, 10) as date', 'COUNT(DISTINCT r.learner) as userCount')
            ->from(Result::class, 'r')
            ->innerJoin('r.learner', 'l')
            ->where('r.created >= :startDate')
            ->andWhere('r.created <= :endDate')
            ->andWhere('l.subscription = :subscription')
            ->andWhere('r.question IS NOT NULL')
            ->setParameter('startDate', $startDate->setTime(0, 0, 0))
            ->setParameter('endDate', $endDate->setTime(23, 59, 59))
            ->setParameter('subscription', 'free')
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}