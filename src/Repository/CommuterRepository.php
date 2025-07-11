<?php

namespace App\Repository;

use App\Entity\Commuter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commuter>
 *
 * @method Commuter|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commuter|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commuter[]    findAll()
 * @method Commuter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommuterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commuter::class);
    }

    public function save(Commuter $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Commuter $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find commuter by UID
     */
    public function findByUid(string $uid): ?Commuter
    {
        return $this->findOneBy(['uid' => $uid]);
    }

    /**
     * Find commuters by type (driver or passenger)
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type], ['created' => 'DESC']);
    }

    /**
     * Find commuters by city
     */
    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.homeCity = :city OR c.workCity = :city')
            ->setParameter('city', $city)
            ->orderBy('c.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find commuters by province
     */
    public function findByProvince(string $province): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.homeProvince = :province OR c.workProvince = :province')
            ->setParameter('province', $province)
            ->orderBy('c.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find commuters with subscription
     */
    public function findWithSubscription(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.subscription IS NOT NULL')
            ->orderBy('c.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find commuters by phone number
     */
    public function findByPhoneNumber(string $phoneNumber): ?Commuter
    {
        return $this->findOneBy(['phoneNumber' => $phoneNumber]);
    }

    /**
     * Find commuters within a certain radius of coordinates
     */
    public function findNearby(float $lat, float $lng, float $radiusKm = 10): array
    {
        // Using Haversine formula to calculate distance
        $sql = "
            SELECT c.*, 
                   (6371 * acos(cos(radians(:lat)) * cos(radians(COALESCE(c.home_lat, c.work_lat))) * 
                    cos(radians(COALESCE(c.home_lng, c.work_lng)) - radians(:lng)) + 
                    sin(radians(:lat)) * sin(radians(COALESCE(c.home_lat, c.work_lat))))) AS distance
            FROM commuters c
            HAVING distance <= :radius
            ORDER BY distance
        ";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radiusKm
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * Find commuter by commute ID
     * 
     * Note: This method assumes you have a way to map commute IDs to commuters.
     * You may need to modify this based on your actual data structure.
     */
    public function findByCommuteId(int $commuteId): ?Commuter
    {
        // TODO: Implement based on your actual data structure
        // This is a placeholder - you need to implement the actual logic
        
        // Example implementations (uncomment and modify as needed):
        
        // Option 1: If commute IDs are stored in a separate field
        // return $this->findOneBy(['commuteId' => $commuteId]);
        
        // Option 2: If you have a separate table mapping commute IDs to commuter UIDs
        // $conn = $this->getEntityManager()->getConnection();
        // $stmt = $conn->prepare('SELECT commuter_uid FROM commute_mappings WHERE commute_id = :commute_id');
        // $result = $stmt->executeQuery(['commute_id' => $commuteId]);
        // $commuterUid = $result->fetchOne();
        // return $commuterUid ? $this->findByUid($commuterUid) : null;
        
        // Option 3: If commute IDs are stored in a JSON field
        // return $this->createQueryBuilder('c')
        //     ->where('JSON_CONTAINS(c.commuteIds, :commute_id)')
        //     ->setParameter('commute_id', $commuteId)
        //     ->getQuery()
        //     ->getOneOrNullResult();
        
        return null;
    }

    /**
     * Get statistics about commuters
     */
    public function getStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $stats = [];
        
        // Total count
        $stmt = $conn->prepare('SELECT COUNT(*) as total FROM commuters');
        $result = $stmt->executeQuery();
        $stats['total'] = $result->fetchAssociative()['total'];
        
        // Count by type
        $stmt = $conn->prepare('SELECT type, COUNT(*) as count FROM commuters GROUP BY type');
        $result = $stmt->executeQuery();
        $stats['by_type'] = $result->fetchAllAssociative();
        
        // Count by province
        $stmt = $conn->prepare('
            SELECT home_province as province, COUNT(*) as count 
            FROM commuters 
            GROUP BY home_province 
            ORDER BY count DESC
        ');
        $result = $stmt->executeQuery();
        $stats['by_province'] = $result->fetchAllAssociative();
        
        // Count with subscription
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM commuters WHERE subscription IS NOT NULL');
        $result = $stmt->executeQuery();
        $stats['with_subscription'] = $result->fetchAssociative()['count'];
        
        return $stats;
    }
} 