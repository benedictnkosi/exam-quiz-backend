<?php

namespace App\Controller;

use App\Entity\Commuter;
use App\Entity\DriverPassengerDistance;
use App\Repository\CommuterRepository;
use App\Repository\DriverPassengerDistanceRepository;
use App\Service\GooglePlacesService;
use App\Service\GoogleDirectionsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use App\Service\OpenAIService;
use App\Service\PushNotificationService;
use App\Service\CommuteDistanceMessageTrackingService;

#[Route('/api/commuters', name: 'commuter_')]
class CommuterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuterRepository $commuterRepository,
        private DriverPassengerDistanceRepository $distanceRepository,
        private GooglePlacesService $googlePlacesService,
        private GoogleDirectionsService $googleDirectionsService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
        private OpenAIService $openAIService,
        private PushNotificationService $pushNotificationService,
        private CommuteDistanceMessageTrackingService $messageTrackingService
    ) {}

    /**
     * Create a new commuter
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'error' => 'Invalid JSON data',
                    'message' => 'Please provide valid JSON data'
                ], 400);
            }

            // Validate required fields
            $requiredFields = ['name', 'phoneNumber', 'homeAddressStreet', 'homeProvince', 'homeCity', 'workAddressStreet', 'workProvince', 'workCity', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json([
                        'error' => 'Missing required field',
                        'message' => "Field '{$field}' is required"
                    ], 400);
                }
            }

            // Validate type
            if (!in_array($data['type'], ['driver', 'passenger'])) {
                return $this->json([
                    'error' => 'Invalid type',
                    'message' => 'Type must be either "driver" or "passenger"'
                ], 400);
            }

            // Validate status if provided
            if (isset($data['status'])) {
                if (!in_array($data['status'], ['active', 'inactive', 'suspended', 'deleted'])) {
                    return $this->json([
                        'error' => 'Invalid status',
                        'message' => 'Status must be one of: "active", "inactive", "suspended", "deleted"'
                    ], 400);
                }
            }

            // Check if phone number already exists
            $existingCommuter = $this->commuterRepository->findByPhoneNumber($data['phoneNumber']);
            if ($existingCommuter) {
                return $this->json([
                    'error' => 'Phone number already exists',
                    'message' => 'A commuter with this phone number already exists'
                ], 409);
            }

            // If uid is provided, check uniqueness
            if (isset($data['uid']) && !empty($data['uid'])) {
                $existingUid = $this->commuterRepository->findByUid($data['uid']);
                if ($existingUid) {
                    return $this->json([
                        'error' => 'UID already exists',
                        'message' => 'A commuter with this UID already exists'
                    ], 409);
                }
            }

            // Create new commuter
            $commuter = new Commuter();
            if (isset($data['uid']) && !empty($data['uid'])) {
                $commuter->setUid($data['uid']);
            }
            $commuter->setName($data['name'])
                    ->setPhoneNumber($data['phoneNumber'])
                    ->setHomeAddressStreet($data['homeAddressStreet'])
                    ->setHomeProvince($data['homeProvince'])
                    ->setHomeCity($data['homeCity'])
                    ->setWorkAddressStreet($data['workAddressStreet'])
                    ->setWorkProvince($data['workProvince'])
                    ->setWorkCity($data['workCity'])
                    ->setType($data['type'])
                    ->setStatus($data['status'] ?? 'active');

            // Set optional fields
            if (isset($data['homeLat'])) $commuter->setHomeLat((float) $data['homeLat']);
            if (isset($data['homeLng'])) $commuter->setHomeLng((float) $data['homeLng']);
            if (isset($data['workLat'])) $commuter->setWorkLat((float) $data['workLat']);
            if (isset($data['workLng'])) $commuter->setWorkLng((float) $data['workLng']);
            if (isset($data['subscription'])) $commuter->setSubscription($data['subscription']);
            if (isset($data['pushNotificationToken'])) $commuter->setPushNotificationToken($data['pushNotificationToken']);

            // Validate entity
            $errors = $this->validator->validate($commuter);
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

            // Save to database
            $this->entityManager->persist($commuter);
            $this->entityManager->flush();

            $this->logger->info('Commuter created', [
                'uid' => $commuter->getUid(),
                'name' => $commuter->getName(),
                'type' => $commuter->getType()
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Commuter created successfully',
                'data' => [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName(),
                    'phoneNumber' => $commuter->getPhoneNumber(),
                    'type' => $commuter->getType(),
                    'status' => $commuter->getStatus(),
                    'created' => $commuter->getCreated()->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (\Exception $e) {
            $this->logger->error('Error creating commuter', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while creating the commuter'
            ], 500);
        }
    }

    /**
     * Get a commuter by UID
     */
    #[Route('/{uid}', name: 'get', methods: ['GET'])]
    public function get(string $uid): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName(),
                    'phoneNumber' => $commuter->getPhoneNumber(),
                    'home' => [
                        'address' => $commuter->getHomeAddressStreet(),
                        'lat' => $commuter->getHomeLat(),
                        'lng' => $commuter->getHomeLng(),
                        'province' => $commuter->getHomeProvince(),
                        'city' => $commuter->getHomeCity()
                    ],
                    'work' => [
                        'address' => $commuter->getWorkAddressStreet(),
                        'lat' => $commuter->getWorkLat(),
                        'lng' => $commuter->getWorkLng(),
                        'province' => $commuter->getWorkProvince(),
                        'city' => $commuter->getWorkCity()
                    ],
                    'type' => $commuter->getType(),
                    'status' => $commuter->getStatus(),
                    'subscription' => $commuter->getSubscription(),
                    'push_notification_token' => $commuter->getPushNotificationToken(),
                    'route_coordinates' => $commuter->getRouteCoordinates(),
                    'created' => $commuter->getCreated()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting commuter', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving the commuter'
            ], 500);
        }
    }

    /**
     * Get all commuters with optional filters
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $type = $request->query->get('type');
            $city = $request->query->get('city');
            $province = $request->query->get('province');
            $subscription = $request->query->get('subscription');
            $limit = (int) $request->query->get('limit', 50);
            $offset = (int) $request->query->get('offset', 0);

            // Apply filters
            if ($type) {
                $commuters = $this->commuterRepository->findByType($type);
            } elseif ($city) {
                $commuters = $this->commuterRepository->findByCity($city);
            } elseif ($province) {
                $commuters = $this->commuterRepository->findByProvince($province);
            } elseif ($subscription === 'true') {
                $commuters = $this->commuterRepository->findWithSubscription();
            } else {
                $commuters = $this->commuterRepository->findAll();
            }

            // Apply pagination
            $total = count($commuters);
            $commuters = array_slice($commuters, $offset, $limit);

            $data = [];
            foreach ($commuters as $commuter) {
                $data[] = [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName(),
                    'phoneNumber' => $commuter->getPhoneNumber(),
                    'homeCity' => $commuter->getHomeCity(),
                    'workCity' => $commuter->getWorkCity(),
                    'type' => $commuter->getType(),
                    'status' => $commuter->getStatus(),
                    'subscription' => $commuter->getSubscription(),
                    'created' => $commuter->getCreated()->format('Y-m-d H:i:s')
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'commuters' => $data,
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'count' => count($data)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error listing commuters', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving commuters'
            ], 500);
        }
    }

    /**
     * Update a commuter
     */
    #[Route('/{uid}', name: 'update', methods: ['PUT'])]
    public function update(string $uid, Request $request): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'error' => 'Invalid JSON data',
                    'message' => 'Please provide valid JSON data'
                ], 400);
            }

            // Update fields if provided
            if (isset($data['name'])) $commuter->setName($data['name']);
            if (isset($data['phoneNumber'])) {
                // Check if phone number is already taken by another commuter
                $existingCommuter = $this->commuterRepository->findByPhoneNumber($data['phoneNumber']);
                if ($existingCommuter && $existingCommuter->getUid() !== $uid) {
                    return $this->json([
                        'error' => 'Phone number already exists',
                        'message' => 'A commuter with this phone number already exists'
                    ], 409);
                }
                $commuter->setPhoneNumber($data['phoneNumber']);
            }
            if (isset($data['homeAddressStreet'])) $commuter->setHomeAddressStreet($data['homeAddressStreet']);
            if (isset($data['homeLat'])) $commuter->setHomeLat((float) $data['homeLat']);
            if (isset($data['homeLng'])) $commuter->setHomeLng((float) $data['homeLng']);
            if (isset($data['homeProvince'])) $commuter->setHomeProvince($data['homeProvince']);
            if (isset($data['homeCity'])) $commuter->setHomeCity($data['homeCity']);
            if (isset($data['workAddressStreet'])) $commuter->setWorkAddressStreet($data['workAddressStreet']);
            if (isset($data['workLat'])) $commuter->setWorkLat((float) $data['workLat']);
            if (isset($data['workLng'])) $commuter->setWorkLng((float) $data['workLng']);
            if (isset($data['workProvince'])) $commuter->setWorkProvince($data['workProvince']);
            if (isset($data['workCity'])) $commuter->setWorkCity($data['workCity']);
            if (isset($data['type'])) {
                if (!in_array($data['type'], ['driver', 'passenger'])) {
                    return $this->json([
                        'error' => 'Invalid type',
                        'message' => 'Type must be either "driver" or "passenger"'
                    ], 400);
                }
                $commuter->setType($data['type']);
            }
            if (isset($data['status'])) {
                if (!in_array($data['status'], ['active', 'inactive', 'suspended', 'deleted'])) {
                    return $this->json([
                        'error' => 'Invalid status',
                        'message' => 'Status must be one of: "active", "inactive", "suspended", "deleted"'
                    ], 400);
                }
                $commuter->setStatus($data['status']);
            }
            if (isset($data['subscription'])) $commuter->setSubscription($data['subscription']);

            // Validate entity
            $errors = $this->validator->validate($commuter);
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

            $this->logger->info('Commuter updated', [
                'uid' => $commuter->getUid(),
                'name' => $commuter->getName()
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Commuter updated successfully',
                'data' => [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName()
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error updating commuter', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while updating the commuter'
            ], 500);
        }
    }

    /**
     * Get commuter statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->commuterRepository->getStats();

            return $this->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting commuter stats', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving statistics'
            ], 500);
        }
    }

    /**
     * Save driver route coordinates
     */
    #[Route('/{uid}/route', name: 'save_route', methods: ['POST'])]
    public function saveRoute(string $uid, Request $request): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            // Only drivers can have routes
            if (!$commuter->isDriver()) {
                return $this->json([
                    'error' => 'Invalid commuter type',
                    'message' => 'Only drivers can have routes'
                ], 400);
            }

            // Get coordinates from commuter's existing data
            $homeLat = $commuter->getHomeLat();
            $homeLng = $commuter->getHomeLng();
            $workLat = $commuter->getWorkLat();
            $workLng = $commuter->getWorkLng();

            // Check if coordinates are available
            if ($homeLat === null || $homeLng === null || $workLat === null || $workLng === null) {
                return $this->json([
                    'error' => 'Missing coordinates',
                    'message' => 'Commuter must have home and work coordinates set before calculating route'
                ], 400);
            }

            // Get optional parameters from request body
            $data = json_decode($request->getContent(), true) ?: [];
            $numPoints = $data['numPoints'] ?? 20;

            // Validate numPoints
            if ($numPoints < 2 || $numPoints > 20) {
                return $this->json([
                    'error' => 'Invalid number of points',
                    'message' => 'Number of points must be between 2 and 20'
                ], 400);
            }

            // Get route coordinates from Google Directions API
            $routeCoordinates = $this->googleDirectionsService->getRouteCoordinates(
                $homeLat,
                $homeLng,
                $workLat,
                $workLng,
                $numPoints
            );

            if (!$routeCoordinates) {
                return $this->json([
                    'error' => 'Route calculation failed',
                    'message' => 'Could not calculate route between home and work locations'
                ], 500);
            }

            // Update commuter with route coordinates
            $commuter->setRouteCoordinates($routeCoordinates);
            $this->entityManager->flush();

            $this->logger->info('Driver route saved', [
                'uid' => $commuter->getUid(),
                'name' => $commuter->getName(),
                'home_coords' => "{$homeLat},{$homeLng}",
                'work_coords' => "{$workLat},{$workLng}",
                'route_points' => count($routeCoordinates)
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Driver route saved successfully',
                'data' => [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName(),
                    'home_coordinates' => [
                        'lat' => $homeLat,
                        'lng' => $homeLng,
                        'address' => $commuter->getHomeAddressStreet()
                    ],
                    'work_coordinates' => [
                        'lat' => $workLat,
                        'lng' => $workLng,
                        'address' => $commuter->getWorkAddressStreet()
                    ],
                    'route_coordinates' => $routeCoordinates,
                    'points_count' => count($routeCoordinates)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error saving driver route', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while saving the driver route'
            ], 500);
        }
    }

    /**
     * Find commuters nearby
     */
    #[Route('/nearby', name: 'nearby', methods: ['GET'])]
    public function nearby(Request $request): JsonResponse
    {
        try {
            $lat = (float) $request->query->get('lat');
            $lng = (float) $request->query->get('lng');
            $radius = (float) $request->query->get('radius', 10);

            if (!$lat || !$lng) {
                return $this->json([
                    'error' => 'Missing coordinates',
                    'message' => 'Latitude and longitude are required'
                ], 400);
            }

            $nearbyCommuters = $this->commuterRepository->findNearby($lat, $lng, $radius);

            return $this->json([
                'success' => true,
                'data' => [
                    'coordinates' => ['lat' => $lat, 'lng' => $lng],
                    'radius_km' => $radius,
                    'commuters' => $nearbyCommuters,
                    'count' => count($nearbyCommuters)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error finding nearby commuters', [
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while finding nearby commuters'
            ], 500);
        }
    }

    /**
     * Get all opposite type commuters for a given UID with distance filtering
     */
    #[Route('/{uid}/matches', name: 'matches', methods: ['GET'])]
    public function getMatches(string $uid, Request $request): JsonResponse
    {
        try {
            // Get the commuter
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            // Get distance threshold from query parameter (default 5000m)
            $maxDistance = (float) $request->query->get('max_distance', 5000);

            // Determine opposite type
            $oppositeType = $commuter->getType() === 'driver' ? 'passenger' : 'driver';

            // Get all distances within the threshold
            $distances = $this->distanceRepository->findByDriverAndPassengerWithType(
                $commuter->getId(), // Use the integer ID instead of UID
                $oppositeType,
                $maxDistance
            );

            $matches = [];
            foreach ($distances as $distance) {
                // Determine which UID is the opposite commuter
                $oppositeId = null;
                if ($distance->getDriverCommuteId() === $commuter->getId()) {
                    $oppositeId = $distance->getPassengerCommuteId();
                } elseif ($distance->getPassengerCommuteId() === $commuter->getId()) {
                    $oppositeId = $distance->getDriverCommuteId();
                }
                
                if (!$oppositeId) {
                    continue;
                }
                
                $oppositeCommuter = $this->commuterRepository->find($oppositeId);
                
                if ($oppositeCommuter && $oppositeCommuter->getType() === $oppositeType) {
                    $matches[] = [
                        'uid' => $oppositeCommuter->getUid(),
                        'name' => $oppositeCommuter->getName(),
                        'phone_number' => $oppositeCommuter->getPhoneNumber(),
                        'type' => $oppositeCommuter->getType(),
                        'home' => [
                            'street' => $oppositeCommuter->getHomeAddressStreet(),
                            'city' => $oppositeCommuter->getHomeCity(),
                            'province' => $oppositeCommuter->getHomeProvince(),
                            'full_address' => $oppositeCommuter->getHomeAddressStreet() . ', ' . $oppositeCommuter->getHomeCity() . ', ' . $oppositeCommuter->getHomeProvince(),
                            'lat' => $oppositeCommuter->getHomeLat(),
                            'lng' => $oppositeCommuter->getHomeLng()
                        ],
                        'work' => [
                            'street' => $oppositeCommuter->getWorkAddressStreet(),
                            'city' => $oppositeCommuter->getWorkCity(),
                            'province' => $oppositeCommuter->getWorkProvince(),
                            'full_address' => $oppositeCommuter->getWorkAddressStreet() . ', ' . $oppositeCommuter->getWorkCity() . ', ' . $oppositeCommuter->getWorkProvince(),
                            'lat' => $oppositeCommuter->getWorkLat(),
                            'lng' => $oppositeCommuter->getWorkLng()
                        ],
                        'distances' => [
                            'home_distance' => round($distance->getHomeDistance(), 2),
                            'work_distance' => round($distance->getWorkDistance(), 2),
                            'max_distance' => round($distance->getMaxDistance(), 2)
                        ],
                        'calculated_at' => $distance->getCalculatedAt()->format('Y-m-d H:i:s'),
                        'id' => $distance->getId()
                    ];
                }
            }

            // Sort by max distance (closest first)
            usort($matches, function($a, $b) {
                return $a['distances']['max_distance'] <=> $b['distances']['max_distance'];
            });

            $this->logger->info('Commuter matches retrieved', [
                'uid' => $uid,
                'type' => $commuter->getType(),
                'opposite_type' => $oppositeType,
                'max_distance' => $maxDistance,
                'matches_count' => count($matches)
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'commuter' => [
                        'uid' => $commuter->getUid(),
                        'name' => $commuter->getName(),
                        'type' => $commuter->getType(),
                        'home' => [
                            'street' => $commuter->getHomeAddressStreet(),
                            'city' => $commuter->getHomeCity(),
                            'province' => $commuter->getHomeProvince(),
                            'full_address' => $commuter->getHomeAddressStreet() . ', ' . $commuter->getHomeCity() . ', ' . $commuter->getHomeProvince(),
                            'lat' => $commuter->getHomeLat(),
                            'lng' => $commuter->getHomeLng()
                        ],
                        'work' => [
                            'street' => $commuter->getWorkAddressStreet(),
                            'city' => $commuter->getWorkCity(),
                            'province' => $commuter->getWorkProvince(),
                            'full_address' => $commuter->getWorkAddressStreet() . ', ' . $commuter->getWorkCity() . ', ' . $commuter->getWorkProvince(),
                            'lat' => $commuter->getWorkLat(),
                            'lng' => $commuter->getWorkLng()
                        ]
                    ],
                    'opposite_type' => $oppositeType,
                    'max_distance_threshold' => $maxDistance,
                    'matches' => $matches,
                    'count' => count($matches)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting commuter matches', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving matches'
            ], 500);
        }
    }

    /**
     * Check if a message does NOT include a phone number or a street address using OpenAI
     */
    #[Route('/check-message', name: 'check_message', methods: ['POST'])]
    public function checkMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['message']) || empty($data['message'])) {
            return $this->json([
                'error' => 'Missing message',
                'message' => 'The message field is required.'
            ], 400);
        }

        $message = $data['message'];
        $history = $data['history'] ?? [];
        $result = $this->openAIService->checkMessageForPhoneOrAddress($message, $history);
        if (!is_array($result)) {
            return $this->json([
                'error' => 'OpenAI response not understood',
                'openai_content' => $result
            ], 500);
        }
        return $this->json([
            'success' => true,
            'contains_phone_number' => $result['contains_phone_number'] ?? null,
            'contains_street_address' => $result['contains_street_address'] ?? null
        ]);
    }

    /**
     * Get commuter by phone number
     */
    #[Route('/by-phone/{phoneNumber}', name: 'get_by_phone', methods: ['GET'])]
    public function getByPhone(string $phoneNumber): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByPhoneNumber($phoneNumber);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided phone number'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName(),
                    'phoneNumber' => $commuter->getPhoneNumber(),
                    'home' => [
                        'address' => $commuter->getHomeAddressStreet(),
                        'lat' => $commuter->getHomeLat(),
                        'lng' => $commuter->getHomeLng(),
                        'province' => $commuter->getHomeProvince(),
                        'city' => $commuter->getHomeCity()
                    ],
                    'work' => [
                        'address' => $commuter->getWorkAddressStreet(),
                        'lat' => $commuter->getWorkLat(),
                        'lng' => $commuter->getWorkLng(),
                        'province' => $commuter->getWorkProvince(),
                        'city' => $commuter->getWorkCity()
                    ],
                    'type' => $commuter->getType(),
                    'status' => $commuter->getStatus(),
                    'subscription' => $commuter->getSubscription(),
                    'route_coordinates' => $commuter->getRouteCoordinates(),
                    'created' => $commuter->getCreated()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting commuter by phone', [
                'phoneNumber' => $phoneNumber,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while retrieving the commuter'
            ], 500);
        }
    }

    /**
     * Change phone number and address for a commuter
     */
    #[Route('/{uid}/change-contact', name: 'change_contact', methods: ['PATCH'])]
    public function changeContact(string $uid, Request $request): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'error' => 'Invalid JSON data',
                    'message' => 'Please provide valid JSON data'
                ], 400);
            }

            $updatedFields = [];
            $validationErrors = [];

            // Validate and update phone number if provided
            if (isset($data['phoneNumber'])) {
                if (empty($data['phoneNumber'])) {
                    $validationErrors[] = 'Phone number cannot be empty';
                } else {
                    // Check if phone number is already taken by another commuter
                    $existingCommuter = $this->commuterRepository->findByPhoneNumber($data['phoneNumber']);
                    if ($existingCommuter && $existingCommuter->getUid() !== $uid) {
                        return $this->json([
                            'error' => 'Phone number already exists',
                            'message' => 'A commuter with this phone number already exists'
                        ], 409);
                    }
                    $commuter->setPhoneNumber($data['phoneNumber']);
                    $updatedFields[] = 'phoneNumber';
                }
            }

            // Validate and update home address if provided
            if (isset($data['homeAddressStreet'])) {
                if (empty($data['homeAddressStreet'])) {
                    $validationErrors[] = 'Home address cannot be empty';
                } else {
                    $commuter->setHomeAddressStreet($data['homeAddressStreet']);
                    $updatedFields[] = 'homeAddressStreet';
                }
            }

            if (isset($data['homeCity'])) {
                if (empty($data['homeCity'])) {
                    $validationErrors[] = 'Home city cannot be empty';
                } else {
                    $commuter->setHomeCity($data['homeCity']);
                    $updatedFields[] = 'homeCity';
                }
            }

            if (isset($data['homeProvince'])) {
                if (empty($data['homeProvince'])) {
                    $validationErrors[] = 'Home province cannot be empty';
                } else {
                    $commuter->setHomeProvince($data['homeProvince']);
                    $updatedFields[] = 'homeProvince';
                }
            }

            // Validate and update work address if provided
            if (isset($data['workAddressStreet'])) {
                if (empty($data['workAddressStreet'])) {
                    $validationErrors[] = 'Work address cannot be empty';
                } else {
                    $commuter->setWorkAddressStreet($data['workAddressStreet']);
                    $updatedFields[] = 'workAddressStreet';
                }
            }

            if (isset($data['workCity'])) {
                if (empty($data['workCity'])) {
                    $validationErrors[] = 'Work city cannot be empty';
                } else {
                    $commuter->setWorkCity($data['workCity']);
                    $updatedFields[] = 'workCity';
                }
            }

            if (isset($data['workProvince'])) {
                if (empty($data['workProvince'])) {
                    $validationErrors[] = 'Work province cannot be empty';
                } else {
                    $commuter->setWorkProvince($data['workProvince']);
                    $updatedFields[] = 'workProvince';
                }
            }

            // Validate and update coordinates if provided
            if (isset($data['homeLat'])) {
                if (!is_numeric($data['homeLat']) || $data['homeLat'] < -90 || $data['homeLat'] > 90) {
                    $validationErrors[] = 'Home latitude must be a valid number between -90 and 90';
                } else {
                    $commuter->setHomeLat((float) $data['homeLat']);
                    $updatedFields[] = 'homeLat';
                }
            }
            if (isset($data['homeLng'])) {
                if (!is_numeric($data['homeLng']) || $data['homeLng'] < -180 || $data['homeLng'] > 180) {
                    $validationErrors[] = 'Home longitude must be a valid number between -180 and 180';
                } else {
                    $commuter->setHomeLng((float) $data['homeLng']);
                    $updatedFields[] = 'homeLng';
                }
            }
            if (isset($data['workLat'])) {
                if (!is_numeric($data['workLat']) || $data['workLat'] < -90 || $data['workLat'] > 90) {
                    $validationErrors[] = 'Work latitude must be a valid number between -90 and 90';
                } else {
                    $commuter->setWorkLat((float) $data['workLat']);
                    $updatedFields[] = 'workLat';
                }
            }
            if (isset($data['workLng'])) {
                if (!is_numeric($data['workLng']) || $data['workLng'] < -180 || $data['workLng'] > 180) {
                    $validationErrors[] = 'Work longitude must be a valid number between -180 and 180';
                } else {
                    $commuter->setWorkLng((float) $data['workLng']);
                    $updatedFields[] = 'workLng';
                }
            }

            // Return error if validation failed
            if (!empty($validationErrors)) {
                return $this->json([
                    'error' => 'Validation failed',
                    'messages' => $validationErrors
                ], 400);
            }

            // Validate and update type if provided
            if (isset($data['type'])) {
                if (!in_array($data['type'], ['driver', 'passenger'])) {
                    $validationErrors[] = 'Type must be either "driver" or "passenger"';
                } else {
                    $commuter->setType($data['type']);
                    $updatedFields[] = 'type';
                }
            }

            // Validate and update status if provided
            if (isset($data['status'])) {
                if (!in_array($data['status'], ['active', 'inactive', 'suspended', 'deleted'])) {
                    $validationErrors[] = 'Status must be one of: "active", "inactive", "suspended", "deleted"';
                } else {
                    $commuter->setStatus($data['status']);
                    $updatedFields[] = 'status';
                }
            }

            // Return error if no fields were provided to update
            if (empty($updatedFields)) {
                return $this->json([
                    'error' => 'No fields to update',
                    'message' => 'Please provide at least one field to update (phoneNumber, homeAddressStreet, homeCity, homeProvince, workAddressStreet, workCity, workProvince, coordinates, type, or status)'
                ], 400);
            }

            // Validate entity
            $errors = $this->validator->validate($commuter);
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

            $this->logger->info('Commuter contact information updated', [
                'uid' => $commuter->getUid(),
                'name' => $commuter->getName(),
                'updated_fields' => $updatedFields
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'uid' => $commuter->getUid(),
                    'name' => $commuter->getName(),
                    'phoneNumber' => $commuter->getPhoneNumber(),
                    'type' => $commuter->getType(),
                    'status' => $commuter->getStatus(),
                    'home' => [
                        'address' => $commuter->getHomeAddressStreet(),
                        'city' => $commuter->getHomeCity(),
                        'province' => $commuter->getHomeProvince(),
                        'lat' => $commuter->getHomeLat(),
                        'lng' => $commuter->getHomeLng()
                    ],
                    'work' => [
                        'address' => $commuter->getWorkAddressStreet(),
                        'city' => $commuter->getWorkCity(),
                        'province' => $commuter->getWorkProvince(),
                        'lat' => $commuter->getWorkLat(),
                        'lng' => $commuter->getWorkLng()
                    ],
                    'updated_fields' => $updatedFields
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error updating profile', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while updating profile'
            ], 500);
        }
    }

    /**
     * Send notification to a specific commuter
     */
    #[Route('/{uid}/send-notification', name: 'send_notification', methods: ['POST'])]
    public function sendNotification(string $uid, Request $request): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['message']) || empty($data['message'])) {
                return $this->json([
                    'error' => 'Missing message',
                    'message' => 'Message field is required'
                ], 400);
            }

            $message = $data['message'];
            $title = $data['title'] ?? 'Notification';
            $pushToken = $commuter->getPushNotificationToken();

            if (!$pushToken) {
                return $this->json([
                    'error' => 'No push token',
                    'message' => 'This commuter does not have a push notification token registered'
                ], 400);
            }

            $notification = [
                'to' => $pushToken,
                'title' => $title,
                'body' => $message,
                'data' => [
                    'type' => 'custom_notification',
                    'commuter_uid' => $uid,
                    'commuter_name' => $commuter->getName(),
                    'timestamp' => time()
                ]
            ];

            // Send the notification
            $result = $this->pushNotificationService->sendPushNotification($notification);

            if ($result['status'] === 'OK') {
                // Update message tracking if commute_distance_id and sender_type are provided
                $commuteDistanceId = $data['commute_distance_id'] ?? null;
                $senderType = $data['sender_type'] ?? null;
                
                if ($commuteDistanceId && $senderType) {
                    $this->messageTrackingService->updateMessageTracking($commuteDistanceId, $senderType);
                }

                $this->logger->info('Notification sent successfully', [
                    'uid' => $uid,
                    'name' => $commuter->getName(),
                    'title' => $title,
                    'message' => $message,
                    'commute_distance_id' => $commuteDistanceId,
                    'sender_type' => $senderType
                ]);

                $responseData = [
                    'uid' => $uid,
                    'name' => $commuter->getName(),
                    'title' => $title,
                    'message' => $message,
                    'push_token' => $pushToken
                ];

                // Add message tracking info if provided
                if ($commuteDistanceId && $senderType) {
                    $responseData['commute_distance_id'] = $commuteDistanceId;
                    $responseData['sender_type'] = $senderType;
                }


                return $this->json([
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'data' => $responseData
                ]);
            } else {
                $this->logger->error('Failed to send notification', [
                    'uid' => $uid,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);

                return $this->json([
                    'error' => 'Notification failed',
                    'message' => 'Failed to send notification: ' . ($result['message'] ?? 'Unknown error'),
                    'details' => $result
                ], 500);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error sending notification', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while sending the notification'
            ], 500);
        }
    }

    /**
     * Update commuter push notification token
     */
    #[Route('/{uid}/notification-token', name: 'update_notification_token', methods: ['PATCH'])]
    public function updateNotificationToken(string $uid, Request $request): JsonResponse
    {
        try {
            $commuter = $this->commuterRepository->findByUid($uid);

            if (!$commuter) {
                return $this->json([
                    'error' => 'Commuter not found',
                    'message' => 'No commuter found with the provided UID'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);
            if (!$data || !isset($data['pushNotificationToken']) || empty($data['pushNotificationToken'])) {
                return $this->json([
                    'error' => 'Missing token',
                    'message' => 'pushNotificationToken is required.'
                ], 400);
            }

            $commuter->setPushNotificationToken($data['pushNotificationToken']);
            $this->entityManager->flush();

            $this->logger->info('Commuter notification token updated', [
                'uid' => $commuter->getUid(),
                'token' => $data['pushNotificationToken']
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Notification token updated successfully',
                'data' => [
                    'uid' => $commuter->getUid(),
                    'push_notification_token' => $commuter->getPushNotificationToken()
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating notification token', [
                'uid' => $uid,
                'message' => $e->getMessage()
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while updating the notification token'
            ], 500);
        }
    }
} 