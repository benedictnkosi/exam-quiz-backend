<?php

namespace App\Repository;

use App\Entity\ReportedQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportedQuestion>
 *
 * @method ReportedQuestion|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReportedQuestion|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReportedQuestion[]    findAll()
 * @method ReportedQuestion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReportedQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportedQuestion::class);
    }

    public function save(ReportedQuestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReportedQuestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 