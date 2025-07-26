<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 *
 * @method News|null find($id, $lockMode = null, $lockVersion = null)
 * @method News|null findOneBy(array $criteria, array $orderBy = null)
 * @method News[]    findAll()
 * @method News[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    public function save(News $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(News $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.country = :country')
            ->setParameter('country', $country)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCountryAndToday(string $country): ?News
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('n')
            ->andWhere('n.country = :country')
            ->andWhere('n.createdAt >= :today')
            ->andWhere('n.createdAt < :tomorrow')
            ->setParameter('country', $country)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findTodayOnly(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        $tomorrow = clone $today;
        $tomorrow->add(new \DateInterval('P1D'));

        return $this->createQueryBuilder('n')
            ->andWhere('n.createdAt >= :today')
            ->andWhere('n.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 