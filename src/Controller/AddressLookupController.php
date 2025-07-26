<?php

namespace App\Controller;

use App\Service\GooglePlacesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\OpenAIService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

#[Route('/api/address', name: 'address_')]
class AddressLookupController extends AbstractController
{
    public function __construct(
        private GooglePlacesService $googlePlacesService,
        private LoggerInterface $logger,
        private OpenAIService $openAIService, // Inject OpenAIService
        private EntityManagerInterface $entityManager // Inject EntityManager
    ) {}

    /**
     * Look up addresses using Google Places API
     */
    #[Route('/lookup', name: 'lookup', methods: ['GET'])]
    public function lookupAddress(Request $request): JsonResponse
    {
        $addressPrefix = $request->query->get('q', '');
        $countryCode = $request->query->get('country', null);
        $types = $request->query->get('types', '');

        // Validate required parameters
        if (empty($addressPrefix)) {
            return $this->json([
                'error' => 'Address prefix is required',
                'message' => 'Please provide a query parameter "q" with at least 5 characters'
            ], 400);
        }

        // Convert types string to array if provided
        $typesArray = [];
        if (!empty($types)) {
            $typesArray = explode(',', $types);
        }

        try {
            $this->logger->info('Address lookup request received', [
                'query' => $addressPrefix,
                'country_code' => $countryCode,
                'types' => $typesArray
            ]);

            $suggestions = $this->googlePlacesService->lookupAddress($addressPrefix, $countryCode, $typesArray);

            $this->logger->info('Address lookup completed', [
                'query' => $addressPrefix,
                'suggestions_count' => count($suggestions)
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'query' => $addressPrefix,
                    'country_code' => $countryCode,
                    'suggestions' => $suggestions,
                    'count' => count($suggestions)
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid input',
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Address lookup error', [
                'message' => $e->getMessage(),
                'query' => $addressPrefix,
                'country_code' => $countryCode
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while looking up addresses'
            ], 500);
        }
    }

    /**
     * Get detailed place information using place_id
     */
    #[Route('/details/{placeId}', name: 'details', methods: ['GET'])]
    public function getPlaceDetails(string $placeId): JsonResponse
    {
        if (empty($placeId)) {
            return $this->json([
                'error' => 'Place ID is required',
                'message' => 'Please provide a valid place_id'
            ], 400);
        }

        try {
            $details = $this->googlePlacesService->getPlaceDetails($placeId);

            if ($details === null) {
                return $this->json([
                    'error' => 'Place not found',
                    'message' => 'No details found for the provided place_id'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $details
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Place details error', [
                'message' => $e->getMessage(),
                'place_id' => $placeId
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while fetching place details'
            ], 500);
        }
    }

    /**
     * Find restaurants and cafes near a given location
     */
    #[Route('/nearby-restaurants-cafes', name: 'nearby_restaurants_cafes', methods: ['GET'])]
    public function findNearbyRestaurantsAndCafes(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius');

        // Pagination parameters
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', default: 20 );
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $nextPageToken = $request->query->get('next_page_token');

        // Validate required parameters
        if ($lat === null || $lng === null || $radius === null) {
            return $this->json([
                'error' => 'Missing parameters',
                'message' => 'Please provide lat, lng, and radius (in km) as query parameters.'
            ], 400);
        }

        if (!is_numeric($lat) || !is_numeric($lng) || !is_numeric($radius)) {
            return $this->json([
                'error' => 'Invalid parameters',
                'message' => 'lat, lng, and radius must be numeric values.'
            ], 400);
        }

        try {
            $serviceResult = $this->googlePlacesService->findNearbyRestaurantsAndCafes((float)$lat, (float)$lng, (float)$radius, $nextPageToken);
            $results = $serviceResult['results'];
            $nextPageTokenOut = $serviceResult['next_page_token'] ?? null;
            $total = count($results);
            $offset = ($page - 1) * $perPage;
            $paginatedResults = array_slice($results, $offset, $perPage);

            return $this->json([
                'success' => true,
                'data' => [
                    'lat' => (float)$lat,
                    'lng' => (float)$lng,
                    'radius_km' => (float)$radius,
                    'results' => $paginatedResults,
                    'count' => count($paginatedResults),
                    'pagination' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $perPage,
                        'has_more' => ($offset + $perPage) < $total
                    ],
                    'next_page_token' => $nextPageTokenOut
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Nearby restaurants/cafes search error', [
                'message' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while searching for nearby restaurants and cafes.'
            ], 500);
        }
    }

    /**
     * Find low carb food in a given restaurant menu
     */
    #[Route('/low-carb-menu', name: 'low_carb_menu', methods: ['POST'])]
    public function findLowCarbMenu(Request $request): JsonResponse
    {
        $restaurantName = $request->request->get('restaurant_name');
        $menu = $request->request->get('menu');

        if (empty($restaurantName) || empty($menu)) {
            return $this->json([
                'error' => 'Missing parameters',
                'message' => 'Please provide restaurant_name and menu (as a string) in the POST body.'
            ], 400);
        }

        try {
            $result = $this->openAIService->generateLowCarbMenu($restaurantName, $menu);
            return $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Low carb menu AI error', [
                'message' => $e->getMessage(),
                'restaurant_name' => $restaurantName
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while generating the low carb menu.'
            ], 500);
        }
    }

    /**
     * Find food in a given restaurant by food type (e.g., low carb, vegetarian) using AI web search
     * Results are cached in the database to avoid repeated AI API calls.
     */
    #[Route('/menu-by-type', name: 'menu_by_type', methods: ['POST'])]
    public function findMenuByType(Request $request): JsonResponse
    {
        $restaurantName = null;
        $foodType = null;

        // Try to parse JSON if content type is application/json
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            if (is_array($data)) {
                $restaurantName = $data['restaurant_name'] ?? null;
                $foodType = $data['food_type'] ?? null;
            }
        } else {
            $restaurantName = $request->request->get('restaurant_name');
            $foodType = $request->request->get('food_type');
        }

        if (empty($restaurantName) || empty($foodType)) {
            return $this->json([
                'error' => 'Missing parameters',
                'message' => 'Please provide restaurant_name and food_type (e.g., low carb, vegetarian) in the POST body.'
            ], 400);
        }

        try {
            // The OpenAIService now caches results in the database
            $result = $this->openAIService->generateMenuByTypeAndRestaurant($restaurantName, $foodType);
            return $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Menu by type AI error', [
                'message' => $e->getMessage(),
                'restaurant_name' => $restaurantName,
                'food_type' => $foodType
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while generating the menu.'
            ], 500);
        }
    }

    /**
     * Get food by type from all restaurants near me (AI web search, no Google Places)
     */
    #[Route('/nearby-menus-by-type', name: 'nearby_menus_by_type', methods: ['POST'])]
    public function getNearbyMenusByType(Request $request): JsonResponse
    {
        $lat = null;
        $lng = null;
        $radius = null;
        $foodType = null;
        $city = null;
        $mealsLimit = 0;

        // Support JSON and form data
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            if (is_array($data)) {
                $lat = $data['lat'] ?? null;
                $lng = $data['lng'] ?? null;
                $radius = $data['radius'] ?? null;
                $foodType = $data['food_type'] ?? null;
                $city = $data['city'] ?? null;
                $mealsLimit = isset($data['meals']) ? (int)$data['meals'] : 0;
            }
        } else {
            $lat = $request->request->get('lat');
            $lng = $request->request->get('lng');
            $radius = $request->request->get('radius');
            $foodType = $request->request->get('food_type');
            $city = $request->request->get('city');
            $mealsLimit = (int) $request->request->get('meals', 0);
        }
        if ($mealsLimit <= 0) {
            $mealsLimit = (int) $request->query->get('meals', 0);
        }

        if (empty($lat) || empty($lng) || empty($radius) || empty($foodType) || empty($city)) {
            return $this->json([
                'error' => 'Missing parameters',
                'message' => 'Please provide lat, lng, radius, city, and food_type in the POST body.'
            ], 400);
        }

        try {
            $placesResult = $this->googlePlacesService->findNearbyRestaurantsAndCafes((float)$lat, (float)$lng, (float)$radius);
            $restaurants = $placesResult['results'] ?? [];
            $restaurants = array_slice($restaurants, 0, length: 5);

            $entityManager = $this->entityManager;
            $repo = $entityManager->getRepository(\App\Entity\RestaurantMenu::class);
            $results = [];
            $pendingRequestIds = [];
            $now = new \DateTime();

            foreach ($restaurants as $restaurant) {
                $restaurantName = $restaurant['name'] ?? null;
                if (!$restaurantName) continue;
                $combinedName = $restaurantName . ', ' . $city;
                $address = $restaurant['vicinity'] ?? ($restaurant['formatted_address'] ?? null);
                $rating = $restaurant['rating'] ?? null;
                $userRatingsTotal = $restaurant['user_ratings_total'] ?? null;
                $priceLevel = $restaurant['price_level'] ?? null;
                $placeId = $restaurant['place_id'] ?? null;
                $types = $restaurant['types'] ?? null;
                $existing = $repo->findOneBy([
                    'restaurantName' => $combinedName,
                    'foodType' => $foodType
                ]);
                if ($existing) {
                    // Update details if missing
                    $existing->setAddress($address);
                    $existing->setRating($rating);
                    $existing->setUserRatingsTotal($userRatingsTotal);
                    $existing->setPriceLevel($priceLevel);
                    $existing->setPlaceId($placeId);
                    $existing->setTypes($types);
                    $entityManager->persist($existing);
                    $entityManager->flush();
                    $resultRow = [
                        'restaurant_name' => $combinedName,
                        'address' => $address,
                        'rating' => $rating,
                        'user_ratings_total' => $userRatingsTotal,
                        'price_level' => $priceLevel,
                        'place_id' => $placeId,
                        'types' => $types,
                        'status' => $existing->getStatus(),
                        'request_id' => $existing->getRequestId(),
                    ];
                    if ($existing->getStatus() === 'ready') {
                        $resultRow['menu'] = $existing->getMenuResult();
                    } elseif ($existing->getStatus() === 'error') {
                        $resultRow['error_message'] = $existing->getErrorMessage();
                    } else {
                        $pendingRequestIds[] = $existing->getRequestId();
                    }
                    $results[] = $resultRow;
                } else {
                    $menu = new \App\Entity\RestaurantMenu();
                    $menu->setRestaurantName($combinedName);
                    $menu->setFoodType($foodType);
                    $menu->setMenuResult([]);
                    $menu->setStatus('pending');
                    $menu->setCreatedAt($now);
                    $menu->setUpdatedAt($now);
                    $menu->setAddress($address);
                    $menu->setRating($rating);
                    $menu->setUserRatingsTotal($userRatingsTotal);
                    $menu->setPriceLevel($priceLevel);
                    $menu->setPlaceId($placeId);
                    $menu->setTypes($types);
                    $uuid = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
                    $menu->setRequestId($uuid);
                    try {
                        $entityManager->persist($menu);
                        $entityManager->flush();
                        $resultRow = [
                            'restaurant_name' => $combinedName,
                            'address' => $address,
                            'rating' => $rating,
                            'user_ratings_total' => $userRatingsTotal,
                            'price_level' => $priceLevel,
                            'place_id' => $placeId,
                            'types' => $types,
                            'status' => 'pending',
                            'request_id' => $uuid,
                        ];
                        $pendingRequestIds[] = $uuid;
                        $results[] = $resultRow;
                    } catch (UniqueConstraintViolationException $e) {
                        $entityManager->clear();
                        $existing = $repo->findOneBy([
                            'restaurantName' => $combinedName,
                            'foodType' => $foodType
                        ]);
                        if ($existing) {
                            $existing->setAddress($address);
                            $existing->setRating($rating);
                            $existing->setUserRatingsTotal($userRatingsTotal);
                            $existing->setPriceLevel($priceLevel);
                            $existing->setPlaceId($placeId);
                            $existing->setTypes($types);
                            $entityManager->persist($existing);
                            $entityManager->flush();
                            $resultRow = [
                                'restaurant_name' => $combinedName,
                                'address' => $address,
                                'rating' => $rating,
                                'user_ratings_total' => $userRatingsTotal,
                                'price_level' => $priceLevel,
                                'place_id' => $placeId,
                                'types' => $types,
                                'status' => $existing->getStatus(),
                                'request_id' => $existing->getRequestId(),
                            ];
                            if ($existing->getStatus() === 'ready') {
                                $resultRow['menu'] = $existing->getMenuResult();
                            } elseif ($existing->getStatus() === 'error') {
                                $resultRow['error_message'] = $existing->getErrorMessage();
                            } else {
                                $pendingRequestIds[] = $existing->getRequestId();
                            }
                            $results[] = $resultRow;
                        }
                    }
                }
            }
            $entityManager->flush();

            if (!empty($pendingRequestIds)) {
                $cmd = 'php ../bin/console app:process-restaurant-menus "' . implode(',', $pendingRequestIds) . '" > /dev/null 2>&1 &';
                shell_exec($cmd);
            }

            if ($mealsLimit > 0) {
                foreach ($results as &$resultRow) {
                    if (isset($resultRow['menu']) && is_array($resultRow['menu'])) {
                        foreach (['starters', 'mains', 'dessert', 'drinks'] as $group) {
                            if (isset($resultRow['menu'][$group]) && is_array($resultRow['menu'][$group])) {
                                $resultRow['menu'][$group] = array_slice($resultRow['menu'][$group], 0, $mealsLimit);
                            }
                        }
                    }
                }
                unset($resultRow);
            }

            return $this->json([
                'success' => true,
                'data' => $results,
                'message' => 'Menu requests are being processed in the background. Poll with request_id for status.'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Nearby menus by type AI error', [
                'message' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'food_type' => $foodType,
                'city' => $city
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while generating the nearby menus.'
            ], 500);
        }
    }

    /**
     * Poll the status of a menu request by request_id
     */
    #[Route('/menu-request-status/{requestId}', name: 'menu_request_status', methods: ['GET'])]
    public function getMenuRequestStatus(string $requestId, Request $request): JsonResponse
    {
        $entityManager = $this->entityManager;
        $repo = $entityManager->getRepository(\App\Entity\RestaurantMenu::class);
        $menu = $repo->findOneBy(['requestId' => $requestId]);
        if (!$menu) {
            return $this->json([
                'error' => 'Not found',
                'message' => 'No menu request found for this request_id.'
            ], 404);
        }
        $response = [
            'restaurant_name' => $menu->getRestaurantName(),
            'food_type' => $menu->getFoodType(),
            'status' => $menu->getStatus(),
            'request_id' => $menu->getRequestId(),
            'address' => $menu->getAddress(),
            'rating' => $menu->getRating(),
            'user_ratings_total' => $menu->getUserRatingsTotal(),
            'price_level' => $menu->getPriceLevel(),
            'place_id' => $menu->getPlaceId(),
            'types' => $menu->getTypes(),
            'menu_prices' => $menu->getMenuPrices(),
        ];
        $mealsLimit = $request->query->getInt('meals', 0);
        if ($menu->getStatus() === 'ready') {
            $menuResult = $menu->getMenuResult();
            if ($mealsLimit > 0 && is_array($menuResult)) {
                foreach (['starters', 'mains', 'dessert', 'drinks'] as $group) {
                    if (isset($menuResult[$group]) && is_array($menuResult[$group])) {
                        $menuResult[$group] = array_slice($menuResult[$group], 0, $mealsLimit);
                    }
                }
            }
            $response['menu'] = $menuResult;
        } elseif ($menu->getStatus() === 'error') {
            $response['error_message'] = $menu->getErrorMessage();
        }
        return $this->json([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Search for restaurants by partial name
     */
    #[Route('/search-restaurants', name: 'search_restaurants', methods: ['GET'])]
    public function searchRestaurantsByName(Request $request): JsonResponse
    {
        $namePrefix = $request->query->get('q', '');
        $countryCode = $request->query->get('country', null);

        if (empty($namePrefix)) {
            return $this->json([
                'error' => 'Restaurant name prefix is required',
                'message' => 'Please provide a query parameter "q" with at least 2 characters'
            ], 400);
        }

        try {
            $results = $this->googlePlacesService->searchRestaurantsByName($namePrefix, $countryCode);
            return $this->json([
                'success' => true,
                'data' => [
                    'query' => $namePrefix,
                    'country_code' => $countryCode,
                    'restaurants' => $results,
                    'count' => count($results)
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid input',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            $this->logger->error('Restaurant name search error', [
                'message' => $e->getMessage(),
                'query' => $namePrefix,
                'country_code' => $countryCode
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while searching for restaurants.'
            ], 500);
        }
    }

    /**
     * Search for restaurants by partial name near a location
     */
    #[Route('/search-nearby-restaurants', name: 'search_nearby_restaurants', methods: ['GET'])]
    public function searchNearbyRestaurantsByName(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius');
        $namePrefix = $request->query->get('q', '');
        $countryCode = $request->query->get('country', null);

        // Pagination parameters
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 20);
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        if ($lat === null || $lng === null || $radius === null || empty($namePrefix)) {
            return $this->json([
                'error' => 'Missing parameters',
                'message' => 'Please provide lat, lng, radius (in km), and q (restaurant name prefix) as query parameters.'
            ], 400);
        }
        if (!is_numeric($lat) || !is_numeric($lng) || !is_numeric($radius)) {
            return $this->json([
                'error' => 'Invalid parameters',
                'message' => 'lat, lng, and radius must be numeric values.'
            ], 400);
        }
        try {
            $results = $this->googlePlacesService->searchNearbyRestaurantsByName((float)$lat, (float)$lng, (float)$radius, $namePrefix, $countryCode);
            $total = count($results);
            $offset = ($page - 1) * $perPage;
            $paginatedResults = array_slice($results, $offset, $perPage);

            // next_page_token is not supported for name search, so always null
            $nextPageTokenOut = null;

            return $this->json([
                'success' => true,
                'data' => [
                    'lat' => (float)$lat,
                    'lng' => (float)$lng,
                    'radius_km' => (float)$radius,
                    'results' => $paginatedResults,
                    'count' => count($paginatedResults),
                    'pagination' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $perPage,
                        'has_more' => ($offset + $perPage) < $total
                    ],
                    'next_page_token' => $nextPageTokenOut
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid input',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            $this->logger->error('Nearby restaurant name search error', [
                'message' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'query' => $namePrefix,
                'country_code' => $countryCode
            ]);
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while searching for nearby restaurants.'
            ], 500);
        }
    }

    /**
     * Health check endpoint for the address lookup service
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'service' => 'Address Lookup API',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }
} 