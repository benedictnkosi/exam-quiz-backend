<?php

namespace App\Repository;

use App\Entity\LanguageLearnerProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LanguageLearnerProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LanguageLearnerProgress::class);
    }

    public function findByLearnerAndLanguage(int $learnerId, string $language): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.learner = :learnerId')
            ->andWhere('p.language = :language')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('language', $language)
            ->orderBy('p.lastUpdate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByLearnerAndUnit(int $learnerId, int $unitId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.learner = :learnerId')
            ->andWhere('p.unit = :unitId')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('unitId', $unitId)
            ->orderBy('p.lastUpdate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}