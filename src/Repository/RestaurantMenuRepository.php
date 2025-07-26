<?php

namespace App\Repository;

use App\Entity\RestaurantMenu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RestaurantMenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantMenu::class);
    }

    /**
     * Find all menus by an array of requestIds and status (pending/processing)
     *
     * @param array $requestIds
     * @param array $statuses
     * @return RestaurantMenu[]
     */
    public function findByRequestIdsAndStatus(array $requestIds, array $statuses = ['pending', 'processing']): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.requestId IN (:requestIds)')
            ->andWhere('m.status IN (:statuses)')
            ->setParameter('requestIds', $requestIds)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getResult();
    }
} 