<?php

namespace App\Repository;

use App\Entity\Tender;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tender>
 *
 * @method Tender|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tender|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tender[]    findAll()
 * @method Tender[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TenderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tender::class);
    }

    public function save(Tender $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tender $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find tenders by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.category = :category')
            ->setParameter('category', $category)
            ->orderBy('t.closingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tenders by province
     */
    public function findByProvince(string $province): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.province = :province')
            ->setParameter('province', $province)
            ->orderBy('t.closingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tenders by organ of state
     */
    public function findByOrganOfState(string $organOfState): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.organOfState = :organOfState')
            ->setParameter('organOfState', $organOfState)
            ->orderBy('t.closingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tenders closing before a specific date
     */
    public function findClosingBefore(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.closingDate <= :date')
            ->setParameter('date', $date)
            ->orderBy('t.closingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tenders closing after a specific date
     */
    public function findClosingAfter(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.closingDate >= :date')
            ->setParameter('date', $date)
            ->orderBy('t.closingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tenders by tender number
     */
    public function findByTenderNumber(string $tenderNumber): ?Tender
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.tenderNumber = :tenderNumber')
            ->setParameter('tenderNumber', $tenderNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active tenders (not yet closed)
     */
    public function findActiveTenders(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.closingDate > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('t.closingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find awarded tenders
     */
    public function findAwardedTenders(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.awardedAt IS NOT NULL')
            ->orderBy('t.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 