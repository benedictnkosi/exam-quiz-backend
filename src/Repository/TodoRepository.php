<?php

namespace App\Repository;

use App\Entity\Todo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TodoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Todo::class);
    }

    public function findByLearner(int $learnerId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByLearnerAndSubject(int $learnerId, string $subjectName): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.learner = :learnerId')
            ->andWhere('t.subjectName = :subjectName')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('subjectName', $subjectName)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByLearnerAndStatus(int $learnerId, string $status): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.learner = :learnerId')
            ->andWhere('t.status = :status')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function createTodo(Learner $learner, string $title, ?\DateTimeImmutable $dueDate = null): Todo
    {
        $todo = new Todo();
        $todo->setLearner($learner);
        $todo->setTitle($title);
        $todo->setDueDate($dueDate);

        $this->getEntityManager()->persist($todo);
        $this->getEntityManager()->flush();

        return $todo;
    }
} 