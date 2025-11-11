<?php

namespace App\Repository;

use App\Entity\WordReplacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WordReplacement>
 */
class WordReplacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WordReplacement::class);
    }

    /**
     * @return WordReplacement[]
     */
    public function findActiveReplacements(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.active = :active')
            ->setParameter('active', true)
            ->orderBy('w.word', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

