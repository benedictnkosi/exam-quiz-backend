<?php

namespace App\Repository;

use App\Entity\StoryArc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoryArc>
 *
 * @method StoryArc|null find($id, $lockMode = null, $lockVersion = null)
 * @method StoryArc|null findOneBy(array $criteria, array $orderBy = null)
 * @method StoryArc[]    findAll()
 * @method StoryArc[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StoryArcRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoryArc::class);
    }

    public function save(StoryArc $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(StoryArc $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}