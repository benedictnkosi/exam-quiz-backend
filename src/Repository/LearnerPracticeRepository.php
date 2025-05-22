<?php

namespace App\Repository;

use App\Entity\LearnerPractice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerPracticeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerPractice::class);
    }

    public function save(LearnerPractice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LearnerPractice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByLearnerAndSubject(int $learnerId, string $subjectName): ?LearnerPractice
    {
        return $this->createQueryBuilder('lp')
            ->andWhere('lp.learner = :learnerId')
            ->andWhere('lp.subject_name = :subjectName')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('subjectName', $subjectName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function updateLastSeen(int $learnerId, string $subjectName): void
    {
        $this->createQueryBuilder('lp')
            ->update()
            ->set('lp.last_seen', ':now')
            ->where('lp.learner = :learnerId')
            ->andWhere('lp.subject_name = :subjectName')
            ->setParameter('now', new \DateTime())
            ->setParameter('learnerId', $learnerId)
            ->setParameter('subjectName', $subjectName)
            ->getQuery()
            ->execute();
    }
}