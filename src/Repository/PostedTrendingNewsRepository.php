<?php

namespace App\Repository;

use App\Entity\PostedTrendingNews;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostedTrendingNews>
 *
 * @method PostedTrendingNews|null find($id, $lockMode = null, $lockVersion = null)
 * @method PostedTrendingNews|null findOneBy(array $criteria, array $orderBy = null)
 * @method PostedTrendingNews[]    findAll()
 * @method PostedTrendingNews[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PostedTrendingNewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostedTrendingNews::class);
    }

    public function save(PostedTrendingNews $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PostedTrendingNews $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Check if a politician has been posted in the last 30 days
     */
    public function hasPoliticianBeenPostedRecently(string $politicianName, string $scope = 'global'): bool
    {
        if (empty($politicianName)) {
            return false;
        }

        $thirtyDaysAgo = new \DateTime();
        $thirtyDaysAgo->modify('-30 days');

        $result = $this->createQueryBuilder('ptn')
            ->select('COUNT(ptn.id)')
            ->andWhere('ptn.politicianName = :politicianName')
            ->andWhere('ptn.scope = :scope')
            ->andWhere('ptn.postedAt >= :thirtyDaysAgo')
            ->setParameter('politicianName', $politicianName)
            ->setParameter('scope', $scope)
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Get all politicians posted in the last 30 days for a scope
     */
    public function getRecentlyPostedPoliticians(string $scope = 'global'): array
    {
        $thirtyDaysAgo = new \DateTime();
        $thirtyDaysAgo->modify('-30 days');

        return $this->createQueryBuilder('ptn')
            ->select('ptn.politicianName')
            ->andWhere('ptn.scope = :scope')
            ->andWhere('ptn.postedAt >= :thirtyDaysAgo')
            ->andWhere('ptn.politicianName IS NOT NULL')
            ->setParameter('scope', $scope)
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get posted trending news by scope and date range
     */
    public function findByScopeAndDateRange(string $scope, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('ptn')
            ->andWhere('ptn.scope = :scope')
            ->andWhere('ptn.postedAt >= :startDate')
            ->andWhere('ptn.postedAt <= :endDate')
            ->setParameter('scope', $scope)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ptn.postedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the latest posted trending news for a scope
     */
    public function findLatestByScope(string $scope, int $limit = 10): array
    {
        return $this->createQueryBuilder('ptn')
            ->andWhere('ptn.scope = :scope')
            ->setParameter('scope', $scope)
            ->orderBy('ptn.postedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a specific tweet summary has been posted recently (to avoid duplicates)
     */
    public function hasSummaryBeenPostedRecently(string $summary, string $scope = 'global', int $days = 7): bool
    {
        $daysAgo = new \DateTime();
        $daysAgo->modify("-{$days} days");

        $result = $this->createQueryBuilder('ptn')
            ->select('COUNT(ptn.id)')
            ->andWhere('ptn.twitterSummary = :summary')
            ->andWhere('ptn.scope = :scope')
            ->andWhere('ptn.postedAt >= :daysAgo')
            ->setParameter('summary', $summary)
            ->setParameter('scope', $scope)
            ->setParameter('daysAgo', $daysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
} 