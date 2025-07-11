<?php

namespace App\Repository;

use App\Entity\CommuteDistanceMessages;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommuteDistanceMessages>
 *
 * @method CommuteDistanceMessages|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommuteDistanceMessages|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommuteDistanceMessages[]    findAll()
 * @method CommuteDistanceMessages[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommuteDistanceMessagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommuteDistanceMessages::class);
    }

    /**
     * Find message tracking record by commute distance ID
     */
    public function findByCommuteDistanceId(int $commuteDistanceId): ?CommuteDistanceMessages
    {
        return $this->findOneBy(['commuteDistanceId' => $commuteDistanceId]);
    }

    /**
     * Find or create message tracking record for a commute distance
     */
    public function findOrCreateByCommuteDistanceId(int $commuteDistanceId): CommuteDistanceMessages
    {
        $record = $this->findByCommuteDistanceId($commuteDistanceId);
        
        if (!$record) {
            $record = new CommuteDistanceMessages();
            $record->setCommuteDistanceId($commuteDistanceId);
        }
        
        return $record;
    }

    /**
     * Update passenger last message date
     */
    public function updatePassengerLastMessageDate(int $commuteDistanceId, \DateTimeInterface $date): void
    {
        $record = $this->findOrCreateByCommuteDistanceId($commuteDistanceId);
        $record->setPassengerLastMessageDate($date);
        
        $this->_em->persist($record);
        $this->_em->flush();
    }

    /**
     * Update driver last message date
     */
    public function updateDriverLastMessageDate(int $commuteDistanceId, \DateTimeInterface $date): void
    {
        $record = $this->findOrCreateByCommuteDistanceId($commuteDistanceId);
        $record->setDriverLastMessageDate($date);
        
        $this->_em->persist($record);
        $this->_em->flush();
    }

    /**
     * Get message tracking statistics
     */
    public function getMessageTrackingStats(): array
    {
        $qb = $this->createQueryBuilder('cdm');
        
        $stats = $qb
            ->select('
                COUNT(cdm.id) as total_records,
                COUNT(cdm.passengerLastMessageDate) as records_with_passenger_messages,
                COUNT(cdm.driverLastMessageDate) as records_with_driver_messages,
                AVG(DATEDIFF(CURRENT_TIMESTAMP(), cdm.passengerLastMessageDate)) as avg_days_since_passenger_message,
                AVG(DATEDIFF(CURRENT_TIMESTAMP(), cdm.driverLastMessageDate)) as avg_days_since_driver_message
            ')
            ->getQuery()
            ->getSingleResult();
            
        return $stats;
    }

    /**
     * Find commute distances with recent messages (within specified days)
     */
    public function findWithRecentMessages(int $days = 7): array
    {
        $date = new \DateTime("-{$days} days");
        
        $qb = $this->createQueryBuilder('cdm');
        
        return $qb
            ->where('cdm.passengerLastMessageDate >= :date OR cdm.driverLastMessageDate >= :date')
            ->setParameter('date', $date)
            ->orderBy('cdm.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find commute distances with no recent messages (older than specified days)
     */
    public function findWithNoRecentMessages(int $days = 7): array
    {
        $date = new \DateTime("-{$days} days");
        
        $qb = $this->createQueryBuilder('cdm');
        
        return $qb
            ->where('cdm.passengerLastMessageDate < :date OR cdm.passengerLastMessageDate IS NULL')
            ->andWhere('cdm.driverLastMessageDate < :date OR cdm.driverLastMessageDate IS NULL')
            ->setParameter('date', $date)
            ->orderBy('cdm.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 