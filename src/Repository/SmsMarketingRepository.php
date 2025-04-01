<?php

namespace App\Repository;

use App\Entity\SmsMarketing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SmsMarketing>
 *
 * @method SmsMarketing|null find($id, $lockMode = null, $lockVersion = null)
 * @method SmsMarketing|null findOneBy(array $criteria, array $orderBy = null)
 * @method SmsMarketing[]    findAll()
 * @method SmsMarketing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SmsMarketingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SmsMarketing::class);
    }

    public function save(SmsMarketing $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SmsMarketing $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find numbers that haven't received an SMS in the last 30 days
     */
    public function findEligibleNumbersForMarketing(int $limit = 100): array
    {
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');

        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->andWhere('s.lastSmsSentAt IS NULL OR s.lastSmsSentAt < :thirtyDaysAgo')
            ->setParameter('active', true)
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}