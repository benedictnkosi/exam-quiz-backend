<?php

namespace App\Repository;

use App\Entity\MathLesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MathLesson>
 *
 * @method MathLesson|null find($id, $lockMode = null, $lockVersion = null)
 * @method MathLesson|null findOneBy(array $criteria, array $orderBy = null)
 * @method MathLesson[]    findAll()
 * @method MathLesson[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MathLessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MathLesson::class);
    }

    public function save(MathLesson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MathLesson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}