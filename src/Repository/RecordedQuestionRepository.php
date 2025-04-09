<?php

namespace App\Repository;

use App\Entity\RecordedQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecordedQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecordedQuestion::class);
    }

    public function findRecordedQuestionIds(): array
    {
        return $this->createQueryBuilder('rq')
            ->select('rq.questionId')
            ->getQuery()
            ->getResult();
    }
} 