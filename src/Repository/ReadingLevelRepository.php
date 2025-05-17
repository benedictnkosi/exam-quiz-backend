<?php

namespace App\Repository;

use App\Entity\ReadingLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingLevel>
 *
 * @method ReadingLevel|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReadingLevel|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReadingLevel[]    findAll()
 * @method ReadingLevel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReadingLevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingLevel::class);
    }

    public function save(ReadingLevel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReadingLevel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}