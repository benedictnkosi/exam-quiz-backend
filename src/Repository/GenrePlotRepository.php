<?php

namespace App\Repository;

use App\Entity\GenrePlot;
use App\Entity\ReadGenres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GenrePlot>
 *
 * @method GenrePlot|null find($id, $lockMode = null, $lockVersion = null)
 * @method GenrePlot|null findOneBy(array $criteria, array $orderBy = null)
 * @method GenrePlot[]    findAll()
 * @method GenrePlot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GenrePlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GenrePlot::class);
    }

    public function save(GenrePlot $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GenrePlot $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active plots for a specific genre
     */
    public function findByGenre(ReadGenres $genre): array
    {
        return $this->createQueryBuilder('gp')
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active plots for a specific genre and subcategory
     */
    public function findByGenreAndSubcategory(ReadGenres $genre, string $subcategory): array
    {
        return $this->createQueryBuilder('gp')
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->andWhere('gp.subcategory = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count plots by genre and subcategory
     */
    public function countByGenreAndSubcategory(ReadGenres $genre, string $subcategory): int
    {
        return $this->createQueryBuilder('gp')
            ->select('COUNT(gp.id)')
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->andWhere('gp.subcategory = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete all plots for a specific genre and subcategory
     */
    public function deleteByGenreAndSubcategory(ReadGenres $genre, string $subcategory): int
    {
        return $this->createQueryBuilder('gp')
            ->delete()
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->andWhere('gp.subcategory = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->getQuery()
            ->execute();
    }

    /**
     * Find all active plots
     */
    public function findActivePlots(): array
    {
        return $this->createQueryBuilder('gp')
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gp.genre', 'ASC')
            ->addOrderBy('gp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find plots by genre name
     */
    public function findByGenreName(string $genreName): array
    {
        return $this->createQueryBuilder('gp')
            ->join('gp.genre', 'g')
            ->andWhere('g.genreName = :genreName')
            ->setParameter('genreName', $genreName)
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete all plots for a specific genre
     */
    public function deleteByGenre(ReadGenres $genre): int
    {
        return $this->createQueryBuilder('gp')
            ->delete()
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->getQuery()
            ->execute();
    }

    /**
     * Count plots by genre
     */
    public function countByGenre(ReadGenres $genre): int
    {
        return $this->createQueryBuilder('gp')
            ->select('COUNT(gp.id)')
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find random plot for a genre
     */
    public function findRandomByGenre(ReadGenres $genre): ?GenrePlot
    {
        $plots = $this->createQueryBuilder('gp')
            ->andWhere('gp.genre = :genre')
            ->setParameter('genre', $genre)
            ->andWhere('gp.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        if (empty($plots)) {
            return null;
        }

        return $plots[array_rand($plots)];
    }
} 