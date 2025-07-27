<?php

namespace App\Repository;

use App\Entity\PoliticianScandal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PoliticianScandal>
 *
 * @method PoliticianScandal|null find($id, $lockMode = null, $lockVersion = null)
 * @method PoliticianScandal|null findOneBy(array $criteria, array $orderBy = null)
 * @method PoliticianScandal[]    findAll()
 * @method PoliticianScandal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PoliticianScandalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoliticianScandal::class);
    }

    public function save(PoliticianScandal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PoliticianScandal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPolitician(string $politician): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.politician = :politician')
            ->setParameter('politician', $politician)
            ->orderBy('ps.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPoliticianAndCountry(string $politician, string $country): ?PoliticianScandal
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.politician = :politician')
            ->andWhere('ps.country = :country')
            ->setParameter('politician', $politician)
            ->setParameter('country', $country)
            ->orderBy('ps.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByPoliticianAndCountryOlderThan(string $politician, string $country, int $days): ?PoliticianScandal
    {
        $dateThreshold = new \DateTime();
        $dateThreshold->modify("-{$days} days");

        return $this->createQueryBuilder('ps')
            ->andWhere('ps.politician = :politician')
            ->andWhere('ps.country = :country')
            ->andWhere('ps.createdAt < :dateThreshold')
            ->setParameter('politician', $politician)
            ->setParameter('country', $country)
            ->setParameter('dateThreshold', $dateThreshold)
            ->orderBy('ps.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.country = :country')
            ->setParameter('country', $country)
            ->orderBy('ps.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getCountryStatistics(): array
    {
        $qb = $this->createQueryBuilder('ps')
            ->select('ps.country')
            ->addSelect('COUNT(ps.id) as scandals')
            ->addSelect('SUM(ps.totalCorruptionScore) as totalScore')
            ->groupBy('ps.country')
            ->orderBy('scandals', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function getCountryStatisticsByCountry(string $country): array
    {
        $qb = $this->createQueryBuilder('ps')
            ->select('ps.country')
            ->addSelect('COUNT(ps.id) as scandals')
            ->addSelect('SUM(ps.totalCorruptionScore) as totalScore')
            ->andWhere('ps.country = :country')
            ->setParameter('country', $country)
            ->groupBy('ps.country');

        return $qb->getQuery()->getResult();
    }
} 