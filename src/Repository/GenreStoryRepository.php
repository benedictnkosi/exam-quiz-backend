<?php

namespace App\Repository;

use App\Entity\GenrePlot;
use App\Entity\GenreStory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GenreStory>
 *
 * @method GenreStory|null find($id, $lockMode = null, $lockVersion = null)
 * @method GenreStory|null findOneBy(array $criteria, array $orderBy = null)
 * @method GenreStory[]    findAll()
 * @method GenreStory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GenreStoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GenreStory::class);
    }

    public function save(GenreStory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GenreStory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active stories for a specific plot
     */
    public function findByPlot(GenrePlot $plot): array
    {
        return $this->createQueryBuilder('gs')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.ageGroup', 'ASC')
            ->addOrderBy('gs.chapterNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all chapters for a specific plot and age group
     */
    public function findByPlotAndAgeGroup(GenrePlot $plot, string $ageGroup): array
    {
        return $this->createQueryBuilder('gs')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.chapterNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find specific chapter by plot, age group, and chapter number
     */
    public function findByPlotAgeGroupAndChapter(GenrePlot $plot, string $ageGroup, int $chapterNumber): ?GenreStory
    {
        return $this->createQueryBuilder('gs')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.chapterNumber = :chapterNumber')
            ->setParameter('chapterNumber', $chapterNumber)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active stories
     */
    public function findActiveStories(): array
    {
        return $this->createQueryBuilder('gs')
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find stories by age group
     */
    public function findByAgeGroup(string $ageGroup): array
    {
        return $this->createQueryBuilder('gs')
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find stories by genre name and age group
     */
    public function findByGenreNameAndAgeGroup(string $genreName, string $ageGroup): array
    {
        return $this->createQueryBuilder('gs')
            ->join('gs.plot', 'p')
            ->join('p.genre', 'g')
            ->andWhere('g.genreName = :genreName')
            ->setParameter('genreName', $genreName)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.chapterNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find complete story (all chapters) by plot and age group
     */
    public function findCompleteStoryByPlotAndAgeGroup(GenrePlot $plot, string $ageGroup): array
    {
        return $this->createQueryBuilder('gs')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.chapterNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if all chapters exist for a plot and age group
     */
    public function hasCompleteStory(GenrePlot $plot, string $ageGroup): bool
    {
        $count = $this->createQueryBuilder('gs')
            ->select('COUNT(gs.id)')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count >= 5; // Assuming 5 chapters per story
    }

    /**
     * Get the next chapter number for a plot and age group
     */
    public function getNextChapterNumber(GenrePlot $plot, string $ageGroup): int
    {
        $maxChapter = $this->createQueryBuilder('gs')
            ->select('MAX(gs.chapterNumber)')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $maxChapter ? $maxChapter + 1 : 1;
    }

    /**
     * Delete all stories for a specific plot
     */
    public function deleteByPlot(GenrePlot $plot): int
    {
        return $this->createQueryBuilder('gs')
            ->delete()
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->getQuery()
            ->execute();
    }

    /**
     * Count stories by plot
     */
    public function countByPlot(GenrePlot $plot): int
    {
        return $this->createQueryBuilder('gs')
            ->select('COUNT(gs.id)')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count chapters by plot and age group
     */
    public function countChaptersByPlotAndAgeGroup(GenrePlot $plot, string $ageGroup): int
    {
        return $this->createQueryBuilder('gs')
            ->select('COUNT(gs.id)')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find random story for a plot and age group
     */
    public function findRandomByPlotAndAgeGroup(GenrePlot $plot, string $ageGroup): ?GenreStory
    {
        $stories = $this->createQueryBuilder('gs')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        if (empty($stories)) {
            return null;
        }

        return $stories[array_rand($stories)];
    }

    /**
     * Get statistics by age group
     */
    public function getStatsByAgeGroup(): array
    {
        $stats = [];
        $ageGroups = GenreStory::getAgeGroups();

        foreach ($ageGroups as $ageGroup) {
            $count = $this->createQueryBuilder('gs')
                ->select('COUNT(gs.id)')
                ->andWhere('gs.ageGroup = :ageGroup')
                ->setParameter('ageGroup', $ageGroup)
                ->andWhere('gs.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult();

            $stats[$ageGroup] = $count;
        }

        return $stats;
    }

    /**
     * Get the latest accumulative summary for a plot and age group
     */
    public function getLatestAccumulativeSummary(GenrePlot $plot, string $ageGroup): ?string
    {
        $story = $this->createQueryBuilder('gs')
            ->select('gs.accumulativeSummary')
            ->andWhere('gs.plot = :plot')
            ->setParameter('plot', $plot)
            ->andWhere('gs.ageGroup = :ageGroup')
            ->setParameter('ageGroup', $ageGroup)
            ->andWhere('gs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('gs.chapterNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $story ? $story['accumulativeSummary'] : null;
    }
} 