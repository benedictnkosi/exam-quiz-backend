<?php

namespace App\Repository;

use App\Entity\TenderBidWinner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenderBidWinner>
 *
 * @method TenderBidWinner|null find($id, $lockMode = null, $lockVersion = null)
 * @method TenderBidWinner|null findOneBy(array $criteria, array $orderBy = null)
 * @method TenderBidWinner[]    findAll()
 * @method TenderBidWinner[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TenderBidWinnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenderBidWinner::class);
    }

    public function save(TenderBidWinner $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TenderBidWinner $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find bid winners by tender ID
     */
    public function findByTenderId(int $tenderId): array
    {
        return $this->createQueryBuilder('tbw')
            ->andWhere('tbw.tender = :tenderId')
            ->setParameter('tenderId', $tenderId)
            ->orderBy('tbw.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bid winners by company ID
     */
    public function findByCompanyId(int $companyId): array
    {
        return $this->createQueryBuilder('tbw')
            ->andWhere('tbw.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('tbw.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bid winners by company name
     */
    public function findByCompanyName(string $companyName): array
    {
        return $this->createQueryBuilder('tbw')
            ->andWhere('tbw.companyName LIKE :companyName')
            ->setParameter('companyName', '%' . $companyName . '%')
            ->orderBy('tbw.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bid winner by tender and company
     */
    public function findByTenderAndCompany(int $tenderId, int $companyId): ?TenderBidWinner
    {
        return $this->createQueryBuilder('tbw')
            ->andWhere('tbw.tender = :tenderId')
            ->andWhere('tbw.company = :companyId')
            ->setParameter('tenderId', $tenderId)
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get companies that have won tenders
     */
    public function findWinningCompanies(): array
    {
        return $this->createQueryBuilder('tbw')
            ->select('DISTINCT tbw.company, tbw.companyName')
            ->orderBy('tbw.companyName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent bid winners
     */
    public function findRecentBidWinners(int $limit = 10): array
    {
        return $this->createQueryBuilder('tbw')
            ->orderBy('tbw.created', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
} 