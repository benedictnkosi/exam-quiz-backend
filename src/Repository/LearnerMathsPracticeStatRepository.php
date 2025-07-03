<?php

namespace App\Repository;

use App\Entity\LearnerMathsPracticeStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerMathsPracticeStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerMathsPracticeStat::class);
    }
} 