<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class QuestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getFirstUnpostedQuestion(): ?Question
    {
        return $this->entityManager->getRepository(Question::class)
            ->createQueryBuilder('q')
            ->where('q.posted = :posted')
            ->setParameter('posted', false)
            ->orderBy('q.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}