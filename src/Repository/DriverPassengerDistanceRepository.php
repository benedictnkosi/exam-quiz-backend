<?php

namespace App\Repository;

use App\Entity\DriverPassengerDistance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DriverPassengerDistance>
 *
 * @method DriverPassengerDistance|null find($id, $lockMode = null, $lockVersion = null)
 * @method DriverPassengerDistance|null findOneBy(array $criteria, array $orderBy = null)
 * @method DriverPassengerDistance[]    findAll()
 * @method DriverPassengerDistance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DriverPassengerDistanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DriverPassengerDistance::class);
    }

    public function save(DriverPassengerDistance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DriverPassengerDistance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Check if distance calculation exists for a driver-passenger pair
     */
    public function existsByDriverAndPassenger(int $driverCommuteId, int $passengerCommuteId): bool
    {
        $result = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.driverCommuteId = :driverCommuteId')
            ->andWhere('d.passengerCommuteId = :passengerCommuteId')
            ->andWhere('d.status = :status')
            ->setParameter('driverCommuteId', $driverCommuteId)
            ->setParameter('passengerCommuteId', $passengerCommuteId)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Find distance calculation for a driver-passenger pair
     */
    public function findByDriverAndPassenger(int $driverCommuteId, int $passengerCommuteId): ?DriverPassengerDistance
    {
        return $this->findOneBy([
            'driverCommuteId' => $driverCommuteId,
            'passengerCommuteId' => $passengerCommuteId,
            'status' => 'active'
        ]);
    }

    /**
     * Find all distances for a specific driver
     */
    public function findByDriver(int $driverCommuteId, ?float $maxDistance = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.driverCommuteId = :driverCommuteId')
            ->andWhere('d.status = :status')
            ->setParameter('driverCommuteId', $driverCommuteId)
            ->setParameter('status', 'active')
            ->orderBy('d.maxDistance', 'ASC');

        if ($maxDistance !== null) {
            $qb->andWhere('d.maxDistance <= :maxDistance')
               ->setParameter('maxDistance', $maxDistance);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all distances for a specific passenger
     */
    public function findByPassenger(int $passengerCommuteId, ?float $maxDistance = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.passengerCommuteId = :passengerCommuteId')
            ->andWhere('d.status = :status')
            ->setParameter('passengerCommuteId', $passengerCommuteId)
            ->setParameter('status', 'active')
            ->orderBy('d.maxDistance', 'ASC');

        if ($maxDistance !== null) {
            $qb->andWhere('d.maxDistance <= :maxDistance')
               ->setParameter('maxDistance', $maxDistance);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all calculated driver UIDs
     */
    public function getCalculatedDriverUids(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('DISTINCT d.driverCommuteId')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'driverCommuteId');
    }

    /**
     * Get all calculated passenger UIDs
     */
    public function getCalculatedPassengerUids(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('DISTINCT d.passengerCommuteId')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'passengerCommuteId');
    }

    /**
     * Get statistics about distance calculations
     */
    public function getStatistics(): array
    {
        $totalCalculations = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $uniqueDrivers = $this->createQueryBuilder('d')
            ->select('COUNT(DISTINCT d.driverCommuteId)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $uniquePassengers = $this->createQueryBuilder('d')
            ->select('COUNT(DISTINCT d.passengerCommuteId)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $avgHomeDistance = $this->createQueryBuilder('d')
            ->select('AVG(d.homeDistance)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $avgWorkDistance = $this->createQueryBuilder('d')
            ->select('AVG(d.workDistance)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $avgMaxDistance = $this->createQueryBuilder('d')
            ->select('AVG(d.maxDistance)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $minMaxDistance = $this->createQueryBuilder('d')
            ->select('MIN(d.maxDistance)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $maxMaxDistance = $this->createQueryBuilder('d')
            ->select('MAX(d.maxDistance)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_calculations' => (int) $totalCalculations,
            'unique_drivers' => (int) $uniqueDrivers,
            'unique_passengers' => (int) $uniquePassengers,
            'average_home_distance' => round((float) $avgHomeDistance, 2),
            'average_work_distance' => round((float) $avgWorkDistance, 2),
            'average_max_distance' => round((float) $avgMaxDistance, 2),
            'min_max_distance' => round((float) $minMaxDistance, 2),
            'max_max_distance' => round((float) $maxMaxDistance, 2)
        ];
    }

    /**
     * Delete old calculations (older than specified days)
     */
    public function deleteOldCalculations(int $daysOld): int
    {
        $cutoffDate = new \DateTime("-{$daysOld} days");

        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.calculatedAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Find distances for a specific driver/passenger with opposite type filtering
     */
    public function findByDriverAndPassengerWithType(int $commuterCommuteId, string $oppositeType, float $maxDistance): array
    {
        // For now, we'll return all distances and let the controller filter by type
        // This is a simpler approach that works with the current data structure
        $qb = $this->createQueryBuilder('d')
            ->where('d.maxDistance <= :maxDistance')
            ->andWhere('d.homeDistance <= :maxDistance')
            ->andWhere('d.workDistance <= :maxDistance')
            ->andWhere('d.status = :status')
            ->setParameter('maxDistance', $maxDistance)
            ->setParameter('status', 'active')
            ->orderBy('d.maxDistance', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find distance by ID (for status updates)
     */
    public function findById(int $id): ?DriverPassengerDistance
    {
        return $this->find($id);
    }

    /**
     * Update status of a distance calculation
     */
    public function updateStatus(int $id, string $status): bool
    {
        $distance = $this->find($id);
        if (!$distance) {
            return false;
        }

        $distance->setStatus($status);
        $this->getEntityManager()->flush();
        return true;
    }

    /**
     * Get all distances with a specific status
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Get status statistics
     */
    public function getStatusStatistics(): array
    {
        $activeCount = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $inactiveCount = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', 'inactive')
            ->getQuery()
            ->getSingleScalarResult();

        $suspendedCount = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', 'suspended')
            ->getQuery()
            ->getSingleScalarResult();

        $deletedCount = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', 'deleted')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'active' => (int) $activeCount,
            'inactive' => (int) $inactiveCount,
            'suspended' => (int) $suspendedCount,
            'deleted' => (int) $deletedCount
        ];
    }
} 