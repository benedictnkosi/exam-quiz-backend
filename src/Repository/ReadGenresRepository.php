<?php

namespace App\Repository;

use App\Entity\ReadGenres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadGenres>
 *
 * @method ReadGenres|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReadGenres|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReadGenres[]    findAll()
 * @method ReadGenres[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReadGenresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadGenres::class);
    }

    public function save(ReadGenres $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReadGenres $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active genres
     */
    public function findActiveGenres(): array
    {
        return $this->createQueryBuilder('rg')
            ->andWhere('rg.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('rg.genreName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find genre by name
     */
    public function findByGenreName(string $genreName): ?ReadGenres
    {
        return $this->createQueryBuilder('rg')
            ->andWhere('rg.genreName = :genreName')
            ->setParameter('genreName', $genreName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find genres containing a specific subcategory
     */
    public function findBySubcategory(string $subcategory): array
    {
        return $this->createQueryBuilder('rg')
            ->andWhere('JSON_CONTAINS(rg.subcategories, :subcategory) = 1')
            ->setParameter('subcategory', json_encode($subcategory))
            ->andWhere('rg.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
} 