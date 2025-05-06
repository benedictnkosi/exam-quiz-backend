<?php

namespace App\Repository;

use App\Entity\RequestedSubject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RequestedSubjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequestedSubject::class);
    }

    public function getSubjectRequestCounts(): array
    {
        return $this->createQueryBuilder('rs')
            ->select('rs.subjectName, COUNT(rs.id) as requestCount')
            ->groupBy('rs.subjectName')
            ->orderBy('requestCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}