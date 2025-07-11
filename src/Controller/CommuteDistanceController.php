<?php

namespace App\Controller;

use App\Entity\DriverPassengerDistance;
use App\Repository\DriverPassengerDistanceRepository;
use App\Service\CommuteDistanceMessageTrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/commute-distances', name: 'commute_distance_')]
class CommuteDistanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DriverPassengerDistanceRepository $distanceRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
        private CommuteDistanceMessageTrackingService $messageTrackingService
    ) {}

    /**
     * Get a specific commute distance by ID
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        try {
            $distance = $this->distanceRepository->findById($id);

            if (!$distance) {
                return $this->json([
                    'error' => 'Distance not found',
                    'message' => 'No distance calculation found with the provided ID'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $distance->getId(),
                    'driver_commute_id' => $distance->getDriverCommuteId(),
                    'passenger_commute_id' => $distance->getPassengerCommuteId(),
                    'home_distance' => $distance->getHomeDistance(),
                    'work_distance' => $distance->getWorkDistance(),
                    'max_distance' => $distance->getMaxDistance(),
                    'status' => $distance->getStatus(),
                    'calculated_at' => $distance->getCalculatedAt()->format('Y-m-d H:i:s'),
                    'created_at' => $distance->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $distance->getUpdatedAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting commute distance', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving the distance'
            ], 500);
        }
    }

    /**
     * Get all commute distances with optional filters
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $status = $request->query->get('status');
            $driverId = $request->query->get('driver_id');
            $passengerId = $request->query->get('passenger_id');
            $limit = (int) $request->query->get('limit', 50);
            $offset = (int) $request->query->get('offset', 0);

            $distances = [];
            
            if ($status) {
                $distances = $this->distanceRepository->findByStatus($status);
            } elseif ($driverId) {
                $distances = $this->distanceRepository->findByDriver((int) $driverId);
            } elseif ($passengerId) {
                $distances = $this->distanceRepository->findByPassenger((int) $passengerId);
            } else {
                // Default to active distances only
                $distances = $this->distanceRepository->findByStatus('active');
            }

            // Apply pagination
            $total = count($distances);
            $distances = array_slice($distances, $offset, $limit);

            $data = [];
            foreach ($distances as $distance) {
                $data[] = [
                    'id' => $distance->getId(),
                    'driver_commute_id' => $distance->getDriverCommuteId(),
                    'passenger_commute_id' => $distance->getPassengerCommuteId(),
                    'home_distance' => $distance->getHomeDistance(),
                    'work_distance' => $distance->getWorkDistance(),
                    'max_distance' => $distance->getMaxDistance(),
                    'status' => $distance->getStatus(),
                    'calculated_at' => $distance->getCalculatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'distances' => $data,
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'count' => count($data)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error listing commute distances', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving distances'
            ], 500);
        }
    }

    /**
     * Change status of a commute distance
     */
    #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'])]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        try {
            $distance = $this->distanceRepository->findById($id);

            if (!$distance) {
                return $this->json([
                    'error' => 'Distance not found',
                    'message' => 'No distance calculation found with the provided ID'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['status'])) {
                return $this->json([
                    'error' => 'Missing status',
                    'message' => 'Status field is required'
                ], 400);
            }

            $status = $data['status'];
            $validStatuses = ['active', 'inactive', 'suspended', 'deleted'];

            if (!in_array($status, $validStatuses)) {
                return $this->json([
                    'error' => 'Invalid status',
                    'message' => 'Status must be one of: ' . implode(', ', $validStatuses)
                ], 400);
            }

            $oldStatus = $distance->getStatus();
            $distance->setStatus($status);
            
            // Validate entity
            $errors = $this->validator->validate($distance);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json([
                    'error' => 'Validation failed',
                    'messages' => $errorMessages
                ], 400);
            }

            $this->entityManager->flush();

            $this->logger->info('Commute distance status updated', [
                'id' => $id,
                'old_status' => $oldStatus,
                'new_status' => $status
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => [
                    'id' => $distance->getId(),
                    'driver_commute_id' => $distance->getDriverCommuteId(),
                    'passenger_commute_id' => $distance->getPassengerCommuteId(),
                    'old_status' => $oldStatus,
                    'new_status' => $distance->getStatus(),
                    'updated_at' => $distance->getUpdatedAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error updating commute distance status', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while updating the status'
            ], 500);
        }
    }

    /**
     * Get commute distance statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->distanceRepository->getStatistics();
            $statusStats = $this->distanceRepository->getStatusStatistics();

            return $this->json([
                'success' => true,
                'data' => [
                    'general_stats' => $stats,
                    'status_stats' => $statusStats
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting commute distance stats', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving statistics'
            ], 500);
        }
    }

    /**
     * Get distances for a specific driver
     */
    #[Route('/driver/{driverId}', name: 'driver_distances', methods: ['GET'])]
    public function getDriverDistances(int $driverId, Request $request): JsonResponse
    {
        try {
            $maxDistance = $request->query->get('max_distance');
            $maxDistance = $maxDistance ? (float) $maxDistance : null;

            $distances = $this->distanceRepository->findByDriver($driverId, $maxDistance);

            $data = [];
            foreach ($distances as $distance) {
                $data[] = [
                    'id' => $distance->getId(),
                    'passenger_commute_id' => $distance->getPassengerCommuteId(),
                    'home_distance' => $distance->getHomeDistance(),
                    'work_distance' => $distance->getWorkDistance(),
                    'max_distance' => $distance->getMaxDistance(),
                    'status' => $distance->getStatus(),
                    'calculated_at' => $distance->getCalculatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'driver_id' => $driverId,
                    'distances' => $data,
                    'count' => count($data)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting driver distances', [
                'driver_id' => $driverId,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving driver distances'
            ], 500);
        }
    }

    /**
     * Get distances for a specific passenger
     */
    #[Route('/passenger/{passengerId}', name: 'passenger_distances', methods: ['GET'])]
    public function getPassengerDistances(int $passengerId, Request $request): JsonResponse
    {
        try {
            $maxDistance = $request->query->get('max_distance');
            $maxDistance = $maxDistance ? (float) $maxDistance : null;

            $distances = $this->distanceRepository->findByPassenger($passengerId, $maxDistance);

            $data = [];
            foreach ($distances as $distance) {
                $data[] = [
                    'id' => $distance->getId(),
                    'driver_commute_id' => $distance->getDriverCommuteId(),
                    'home_distance' => $distance->getHomeDistance(),
                    'work_distance' => $distance->getWorkDistance(),
                    'max_distance' => $distance->getMaxDistance(),
                    'status' => $distance->getStatus(),
                    'calculated_at' => $distance->getCalculatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'passenger_id' => $passengerId,
                    'distances' => $data,
                    'count' => count($data)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting passenger distances', [
                'passenger_id' => $passengerId,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving passenger distances'
            ], 500);
        }
    }

    /**
     * Get message tracking for a specific commute distance
     */
    #[Route('/{id}/message-tracking', name: 'get_message_tracking', methods: ['GET'])]
    public function getMessageTracking(int $id): JsonResponse
    {
        try {
            $distance = $this->distanceRepository->findById($id);

            if (!$distance) {
                return $this->json([
                    'error' => 'Distance not found',
                    'message' => 'No distance calculation found with the provided ID'
                ], 404);
            }

            $trackingData = $this->messageTrackingService->getMessageTrackingData($id);

            return $this->json([
                'success' => true,
                'data' => [
                    'commute_distance_id' => $id,
                    'message_tracking' => $trackingData
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting message tracking', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving message tracking'
            ], 500);
        }
    }

    /**
     * Update message tracking for a commute distance
     */
    #[Route('/{id}/message-tracking', name: 'update_message_tracking', methods: ['PATCH'])]
    public function updateMessageTracking(int $id, Request $request): JsonResponse
    {
        try {
            $distance = $this->distanceRepository->findById($id);

            if (!$distance) {
                return $this->json([
                    'error' => 'Distance not found',
                    'message' => 'No distance calculation found with the provided ID'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['sender_type'])) {
                return $this->json([
                    'error' => 'Missing sender_type',
                    'message' => 'sender_type field is required (driver or passenger)'
                ], 400);
            }

            $senderType = $data['sender_type'];
            $messageDate = isset($data['message_date']) ? new \DateTime($data['message_date']) : null;

            if (!in_array($senderType, ['driver', 'passenger'])) {
                return $this->json([
                    'error' => 'Invalid sender_type',
                    'message' => 'sender_type must be either "driver" or "passenger"'
                ], 400);
            }

            $success = $this->messageTrackingService->updateMessageTracking($id, $senderType, $messageDate);

            if ($success) {
                $trackingData = $this->messageTrackingService->getMessageTrackingData($id);

                return $this->json([
                    'success' => true,
                    'message' => 'Message tracking updated successfully',
                    'data' => [
                        'commute_distance_id' => $id,
                        'sender_type' => $senderType,
                        'message_tracking' => $trackingData
                    ]
                ]);
            } else {
                return $this->json([
                    'error' => 'Update failed',
                    'message' => 'Failed to update message tracking'
                ], 500);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error updating message tracking', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while updating message tracking'
            ], 500);
        }
    }

    /**
     * Get message tracking statistics
     */
    #[Route('/message-tracking/stats', name: 'message_tracking_stats', methods: ['GET'])]
    public function getMessageTrackingStats(): JsonResponse
    {
        try {
            $stats = $this->messageTrackingService->getMessageTrackingStats();

            return $this->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting message tracking stats', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving message tracking statistics'
            ], 500);
        }
    }

    /**
     * Get commute distances with recent messages
     */
    #[Route('/message-tracking/recent', name: 'recent_messages', methods: ['GET'])]
    public function getRecentMessages(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->query->get('days', 7);
            $recentMessages = $this->messageTrackingService->findWithRecentMessages($days);

            $data = [];
            foreach ($recentMessages as $tracking) {
                $data[] = [
                    'id' => $tracking->getId(),
                    'commute_distance_id' => $tracking->getCommuteDistanceId(),
                    'passenger_last_message_date' => $tracking->getPassengerLastMessageDate()?->format('Y-m-d H:i:s'),
                    'driver_last_message_date' => $tracking->getDriverLastMessageDate()?->format('Y-m-d H:i:s'),
                    'updated_at' => $tracking->getUpdatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'days' => $days,
                    'recent_messages' => $data,
                    'count' => count($data)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting recent messages', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving recent messages'
            ], 500);
        }
    }

    /**
     * Get commute distances with no recent messages
     */
    #[Route('/message-tracking/inactive', name: 'inactive_messages', methods: ['GET'])]
    public function getInactiveMessages(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->query->get('days', 7);
            $inactiveMessages = $this->messageTrackingService->findWithNoRecentMessages($days);

            $data = [];
            foreach ($inactiveMessages as $tracking) {
                $data[] = [
                    'id' => $tracking->getId(),
                    'commute_distance_id' => $tracking->getCommuteDistanceId(),
                    'passenger_last_message_date' => $tracking->getPassengerLastMessageDate()?->format('Y-m-d H:i:s'),
                    'driver_last_message_date' => $tracking->getDriverLastMessageDate()?->format('Y-m-d H:i:s'),
                    'updated_at' => $tracking->getUpdatedAt()->format('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'days' => $days,
                    'inactive_messages' => $data,
                    'count' => count($data)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting inactive messages', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving inactive messages'
            ], 500);
        }
    }
} 