<?php

namespace App\Repository;

use App\Entity\HeyGenVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeyGenVideo>
 */
class HeyGenVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeyGenVideo::class);
    }

    /**
     * @return HeyGenVideo[]
     */
    public function findPendingUploads(): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.uploaded = :u')
            ->setParameter('u', false)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}


