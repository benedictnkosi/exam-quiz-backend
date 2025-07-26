<?php

namespace App\Repository;

use App\Entity\Politician;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Politician>
 *
 * @method Politician|null find($id, $lockMode = null, $lockVersion = null)
 * @method Politician|null findOneBy(array $criteria, array $orderBy = null)
 * @method Politician[]    findAll()
 * @method Politician[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PoliticianRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Politician::class);
    }

    public function save(Politician $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Politician $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.country = :country')
            ->andWhere('p.trending = :trending')
            ->setParameter('country', $country)
            ->setParameter('trending', false)
            ->orderBy('p.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTrendingByCountry(string $country): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.country = :country')
            ->andWhere('p.trending = :trending')
            ->setParameter('country', $country)
            ->setParameter('trending', true)
            ->orderBy('p.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTrendingTodayOnly(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('p')
            ->andWhere('p.trending = :trending')
            ->andWhere('p.createdAt >= :today')
            ->andWhere('p.createdAt < :tomorrow')
            ->setParameter('trending', true)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('p.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTrendingByCountryAndToday(string $country): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('p')
            ->andWhere('p.country = :country')
            ->andWhere('p.trending = :trending')
            ->andWhere('p.createdAt >= :today')
            ->andWhere('p.createdAt < :tomorrow')
            ->setParameter('country', $country)
            ->setParameter('trending', true)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('p.score', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 