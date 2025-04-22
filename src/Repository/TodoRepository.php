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

    public function findFutureTodosByLearner(int $learnerId): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('t')
            ->andWhere('t.learner = :learnerId')
            ->andWhere('t.dueDate > :now')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('now', $now)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFutureTodosByLearnerAndSubject(int $learnerId, string $subjectName): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('t')
            ->andWhere('t.learner = :learnerId')
            ->andWhere('t.subjectName = :subjectName')
            ->andWhere('t.dueDate > :now')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('subjectName', $subjectName)
            ->setParameter('now', $now)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByLearnerAndSubjectWithinLast30Days(int $learnerId, string $subjectName): array
    {
        $now = new \DateTimeImmutable();
        $thirtyDaysAgo = $now->modify('-30 days');

        return $this->createQueryBuilder('t')
            ->andWhere('t.learner = :learnerId')
            ->andWhere('t.subjectName = :subjectName')
            ->andWhere('t.dueDate > :thirtyDaysAgo')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('subjectName', $subjectName)
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDueDate(\DateTimeInterface $dueDate): array
    {
        $startOfDay = clone $dueDate;
        $startOfDay->setTime(0, 0, 0);

        $endOfDay = clone $dueDate;
        $endOfDay->setTime(23, 59, 59);

        return $this->createQueryBuilder('t')
            ->andWhere('t.dueDate >= :startOfDay')
            ->andWhere('t.dueDate <= :endOfDay')
            ->andWhere('t.status = :status')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->setParameter('status', 'pending')
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function createTodo(Learner $learner, string $title, ?\DateTimeImmutable $dueDate = null): Todo
    {
        $todo = new Todo();
        $todo->setLearner($learner);
        $todo->setTitle($title);

        if ($dueDate !== null) {
            $timezone = new \DateTimeZone('Africa/Johannesburg');
            $dueDate = $dueDate->setTimezone($timezone);
        }
        $todo->setDueDate($dueDate);

        $this->getEntityManager()->persist($todo);
        $this->getEntityManager()->flush();

        return $todo;
    }
}