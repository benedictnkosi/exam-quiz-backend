<?php

namespace App\Service;

use App\Entity\CommuteDistanceMessages;
use App\Entity\DriverPassengerDistance;
use App\Repository\CommuteDistanceMessagesRepository;
use App\Repository\DriverPassengerDistanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CommuteDistanceMessageTrackingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuteDistanceMessagesRepository $messageTrackingRepository,
        private DriverPassengerDistanceRepository $distanceRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Update message tracking when a notification is sent
     * 
     * @param int $commuteDistanceId The ID of the commute distance
     * @param string $senderType Either 'driver' or 'passenger'
     * @param \DateTimeInterface|null $messageDate Optional message date, defaults to current time
     */
    public function updateMessageTracking(int $commuteDistanceId, string $senderType, ?\DateTimeInterface $messageDate = null): bool
    {
        try {
            // Verify the commute distance exists
            $distance = $this->distanceRepository->findById($commuteDistanceId);
            if (!$distance) {
                $this->logger->warning('Attempted to update message tracking for non-existent commute distance', [
                    'commute_distance_id' => $commuteDistanceId,
                    'sender_type' => $senderType
                ]);
                return false;
            }

            $messageDate = $messageDate ?? new \DateTime();

            // Update the appropriate message date based on sender type
            if ($senderType === 'driver') {
                $this->messageTrackingRepository->updateDriverLastMessageDate($commuteDistanceId, $messageDate);
            } elseif ($senderType === 'passenger') {
                $this->messageTrackingRepository->updatePassengerLastMessageDate($commuteDistanceId, $messageDate);
            } else {
                $this->logger->error('Invalid sender type for message tracking update', [
                    'commute_distance_id' => $commuteDistanceId,
                    'sender_type' => $senderType
                ]);
                return false;
            }

            $this->logger->info('Message tracking updated successfully', [
                'commute_distance_id' => $commuteDistanceId,
                'sender_type' => $senderType,
                'message_date' => $messageDate->format('Y-m-d H:i:s')
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error updating message tracking', [
                'commute_distance_id' => $commuteDistanceId,
                'sender_type' => $senderType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get message tracking record for a commute distance
     */
    public function getMessageTracking(int $commuteDistanceId): ?CommuteDistanceMessages
    {
        return $this->messageTrackingRepository->findByCommuteDistanceId($commuteDistanceId);
    }

    /**
     * Get message tracking statistics
     */
    public function getMessageTrackingStats(): array
    {
        return $this->messageTrackingRepository->getMessageTrackingStats();
    }

    /**
     * Find commute distances with recent messages
     */
    public function findWithRecentMessages(int $days = 7): array
    {
        return $this->messageTrackingRepository->findWithRecentMessages($days);
    }

    /**
     * Find commute distances with no recent messages
     */
    public function findWithNoRecentMessages(int $days = 7): array
    {
        return $this->messageTrackingRepository->findWithNoRecentMessages($days);
    }

    /**
     * Get message tracking data for a specific commute distance
     */
    public function getMessageTrackingData(int $commuteDistanceId): ?array
    {
        $tracking = $this->getMessageTracking($commuteDistanceId);
        
        if (!$tracking) {
            return null;
        }

        return [
            'id' => $tracking->getId(),
            'commute_distance_id' => $tracking->getCommuteDistanceId(),
            'passenger_last_message_date' => $tracking->getPassengerLastMessageDate()?->format('Y-m-d H:i:s'),
            'driver_last_message_date' => $tracking->getDriverLastMessageDate()?->format('Y-m-d H:i:s'),
            'created_at' => $tracking->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $tracking->getUpdatedAt()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Bulk update message tracking for multiple commute distances
     */
    public function bulkUpdateMessageTracking(array $updates): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($updates as $update) {
            $commuteDistanceId = $update['commute_distance_id'] ?? null;
            $senderType = $update['sender_type'] ?? null;
            $messageDate = isset($update['message_date']) ? new \DateTime($update['message_date']) : null;

            if (!$commuteDistanceId || !$senderType) {
                $results['failed']++;
                $results['errors'][] = 'Missing required fields: commute_distance_id or sender_type';
                continue;
            }

            $success = $this->updateMessageTracking($commuteDistanceId, $senderType, $messageDate);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to update tracking for commute distance ID: {$commuteDistanceId}";
            }
        }

        return $results;
    }
} 