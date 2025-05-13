<?php

namespace App\Repository;

use App\Entity\ExamPaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamPaper>
 *
 * @method ExamPaper|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExamPaper|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExamPaper[]    findAll()
 * @method ExamPaper[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExamPaperRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamPaper::class);
    }

    public function save(ExamPaper $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExamPaper $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}