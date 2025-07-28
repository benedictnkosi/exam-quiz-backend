<?php

namespace App\Repository;

use App\Entity\CompanyDirector;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyDirector>
 *
 * @method CompanyDirector|null find($id, $lockMode = null, $lockVersion = null)
 * @method CompanyDirector|null findOneBy(array $criteria, array $orderBy = null)
 * @method CompanyDirector[]    findAll()
 * @method CompanyDirector[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompanyDirectorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyDirector::class);
    }

    public function save(CompanyDirector $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CompanyDirector $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find directors by company ID
     */
    public function findByCompanyId(int $companyId): array
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('cd.directorName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find director by ID number
     */
    public function findByIdNumber(string $idNumber): ?CompanyDirector
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.directorIdNumber = :idNumber')
            ->setParameter('idNumber', $idNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find directors by name
     */
    public function findByDirectorName(string $directorName): array
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.directorName LIKE :directorName')
            ->setParameter('directorName', '%' . $directorName . '%')
            ->orderBy('cd.directorName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find director by company and ID number
     */
    public function findByCompanyAndIdNumber(int $companyId, string $idNumber): ?CompanyDirector
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.company = :companyId')
            ->andWhere('cd.directorIdNumber = :idNumber')
            ->setParameter('companyId', $companyId)
            ->setParameter('idNumber', $idNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find director by company and name
     */
    public function findByCompanyAndName(int $companyId, string $directorName): ?CompanyDirector
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.company = :companyId')
            ->andWhere('cd.directorName = :directorName')
            ->setParameter('companyId', $companyId)
            ->setParameter('directorName', $directorName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all unique director names
     */
    public function findUniqueDirectorNames(): array
    {
        return $this->createQueryBuilder('cd')
            ->select('DISTINCT cd.directorName')
            ->orderBy('cd.directorName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get directors who are directors of multiple companies
     */
    public function findDirectorsOfMultipleCompanies(): array
    {
        return $this->createQueryBuilder('cd')
            ->select('cd.directorName, cd.directorIdNumber, COUNT(cd.company) as companyCount')
            ->groupBy('cd.directorName, cd.directorIdNumber')
            ->having('COUNT(cd.company) > 1')
            ->orderBy('companyCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent directors
     */
    public function findRecentDirectors(int $limit = 10): array
    {
        return $this->createQueryBuilder('cd')
            ->orderBy('cd.created', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
} 