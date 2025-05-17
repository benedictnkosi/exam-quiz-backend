<?php

namespace App\Repository;

use App\Entity\LearnerReading;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LearnerReading>
 *
 * @method LearnerReading|null find($id, $lockMode = null, $lockVersion = null)
 * @method LearnerReading|null findOneBy(array $criteria, array $orderBy = null)
 * @method LearnerReading[]    findAll()
 * @method LearnerReading[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LearnerReadingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerReading::class);
    }

    public function save(LearnerReading $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LearnerReading $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}