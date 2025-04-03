<?php

namespace App\Repository;

use App\Entity\LearnerNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerNote::class);
    }

    public function findByLearnerAndSubject(string $uid, string $subjectName): array
    {
        return $this->createQueryBuilder('n')
            ->join('n.learner', 'l')
            ->where('l.uid = :uid')
            ->andWhere('n.subjectName = :subjectName')
            ->setParameter('uid', $uid)
            ->setParameter('subjectName', $subjectName)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByLearnerAndId(string $uid, int $noteId): ?LearnerNote
    {
        return $this->createQueryBuilder('n')
            ->join('n.learner', 'l')
            ->where('l.uid = :uid')
            ->andWhere('n.id = :noteId')
            ->setParameter('uid', $uid)
            ->setParameter('noteId', $noteId)
            ->getQuery()
            ->getOneOrNullResult();
    }
} 