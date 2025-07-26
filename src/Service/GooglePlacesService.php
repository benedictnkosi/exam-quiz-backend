<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GooglePlacesService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $apiUrl = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    public function __construct(
        string $googlePlacesApiKey,
        private readonly LoggerInterface $logger
    ) {
        $this->client = \Symfony\Component\HttpClient\HttpClient::create();
        $this->apiKey = $googlePlacesApiKey;
    }

    /**
     * Look up addresses using Google Places API
     * 
     * @param string $addressPrefix The first part of the address (minimum 5 characters)
     * @param string|null $countryCode Optional country code to restrict results (e.g., 'ZA' for South Africa)
     * @param array $types Optional array of place types to filter results
     * @return array Array of address suggestions
     */
    public function lookupAddress(string $addressPrefix, ?string $countryCode = null, array $types = []): array
    {
        // Validate input - ensure minimum 5 characters
        if (strlen(trim($addressPrefix)) < 5) {
            throw new \InvalidArgumentException('Address prefix must be at least 5 characters long');
        }

        try {
            $params = [
                'input' => trim($addressPrefix),
                'key' => $this->apiKey,
                'types' => 'address', // Restrict to addresses only
                'language' => 'en', // English results
            ];

            // Add country restriction if provided
            if ($countryCode) {
                $params['components'] = "country:{$countryCode}";
            }

            // Add type restrictions if provided
            if (!empty($types)) {
                $params['types'] = implode('|', $types);
            }

            $response = $this->client->request('GET', $this->apiUrl, [
                'query' => $params,
                'timeout' => 10, // 10 second timeout
            ]);

            $data = json_decode($response->getContent(), true);

            // Log the full Google API response for debugging
            $this->logger->info('Google Places API Response', [
                'input' => $addressPrefix,
                'country_code' => $countryCode,
                'http_status' => $response->getStatusCode(),
                'api_status' => $data['status'] ?? 'UNKNOWN',
                'full_response' => $data,
                'predictions_count' => count($data['predictions'] ?? [])
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Google Places API HTTP Error', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $data
                ]);
                return [];
            }

            if (!isset($data['status']) || $data['status'] !== 'OK') {
                $this->logger->warning('Google Places API returned non-OK status', [
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                    'full_response' => $data
                ]);
                return [];
            }

            // Format the results
            $suggestions = [];
            foreach ($data['predictions'] ?? [] as $prediction) {
                // Get place details to include coordinates and address components
                $placeDetails = $this->getPlaceDetails($prediction['place_id']);
                
                $suggestion = [
                    'place_id' => $prediction['place_id'],
                    'description' => $prediction['description'],
                    'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                    'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? '',
                    'types' => $prediction['types'] ?? [],
                    'matched_substrings' => $prediction['matched_substrings'] ?? []
                ];
                
                // Add coordinates if available
                if ($placeDetails && isset($placeDetails['geometry']['location'])) {
                    $suggestion['lat'] = $placeDetails['geometry']['location']['lat'];
                    $suggestion['lng'] = $placeDetails['geometry']['location']['lng'];
                }
                
                // Extract province from address components
                if ($placeDetails && isset($placeDetails['address_components'])) {
                    $suggestion['province'] = $this->extractProvinceFromAddressComponents($placeDetails['address_components']);
                }
                
                $suggestions[] = $suggestion;
            }

            $this->logger->info('Google Places API lookup successful', [
                'input' => $addressPrefix,
                'results_count' => count($suggestions),
                'country_code' => $countryCode
            ]);

            return $suggestions;

        } catch (\Exception $e) {
            $this->logger->error('Google Places API Error', [
                'message' => $e->getMessage(),
                'input' => $addressPrefix,
                'country_code' => $countryCode
            ]);
            
            return [];
        }
    }

    /**
     * Get detailed place information using place_id
     * 
     * @param string $placeId The Google Places place_id
     * @return array|null Detailed place information or null if not found
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        try {
            $params = [
                'place_id' => $placeId,
                'key' => $this->apiKey,
                'fields' => 'formatted_address,geometry,place_id,name,types,address_components'
            ];

            $response = $this->client->request('GET', 'https://maps.googleapis.com/maps/api/place/details/json', [
                'query' => $params,
                'timeout' => 10,
            ]);

            $data = json_decode($response->getContent(), true);

            if ($response->getStatusCode() !== 200 || $data['status'] !== 'OK') {
                $this->logger->error('Google Places Details API Error', [
                    'place_id' => $placeId,
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'error_message' => $data['error_message'] ?? 'No error message provided'
                ]);
                return null;
            }

            $result = $data['result'] ?? null;
            
            if ($result) {
                $this->logger->info('Google Places Details API lookup successful', [
                    'place_id' => $placeId
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Google Places Details API Error', [
                'message' => $e->getMessage(),
                'place_id' => $placeId
            ]);
            
            return null;
        }
    }

    /**
     * Find restaurants and cafes near a given location
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param float $radiusKm Radius in kilometers
     * @param string|null $nextPageToken Optional Google Places next_page_token
     * @return array ['results' => [...], 'next_page_token' => string|null]
     */
    public function findNearbyRestaurantsAndCafes(float $lat, float $lng, float $radiusKm, ?string $nextPageToken = null): array
    {
        $radiusMeters = (int)($radiusKm * 1000);
        $types = 'restaurant|cafe';
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

        $params = [
            'location' => $lat . ',' . $lng,
            'radius' => $radiusMeters,
            'type' => $types,
            'key' => $this->apiKey,
            
        ];
        if ($nextPageToken) {
            $params['pagetoken'] = $nextPageToken;
            // Google requires a short delay before using next_page_token
            sleep(2);
        }
        $response = $this->client->request('GET', $url, [
            'query' => $params,
            'timeout' => 10,
        ]);
        $data = json_decode($response->getContent(), true);
        $this->logger->info('Google Places Nearby Search Response', [
            'lat' => $lat,
            'lng' => $lng,
            'radius_m' => $radiusMeters,
            'http_status' => $response->getStatusCode(),
            'api_status' => $data['status'] ?? 'UNKNOWN',
            'results_count' => count($data['results'] ?? [])
        ]);
        if ($response->getStatusCode() !== 200 || ($data['status'] ?? '') !== 'OK') {
            $this->logger->error('Google Places Nearby Search API Error', [
                'status_code' => $response->getStatusCode(),
                'status' => $data['status'] ?? 'UNKNOWN',
                'error_message' => $data['error_message'] ?? 'No error message provided',
                'response' => $data
            ]);
            return ['results' => [], 'next_page_token' => null];
        }
        return [
            'results' => $data['results'] ?? [],
            'next_page_token' => $data['next_page_token'] ?? null
        ];
    }

    /**
     * Search for restaurants by partial name using Google Places API
     *
     * @param string $namePrefix The partial or full restaurant name
     * @param string|null $countryCode Optional country code to restrict results
     * @return array Array of restaurant suggestions
     */
    public function searchRestaurantsByName(string $namePrefix, ?string $countryCode = null): array
    {
        if (strlen(trim($namePrefix)) < 2) {
            throw new \InvalidArgumentException('Restaurant name prefix must be at least 2 characters long');
        }

        try {
            $params = [
                'input' => trim($namePrefix),
                'key' => $this->apiKey,
                'types' => 'establishment', // establishment includes restaurants, cafes, etc.
                'language' => 'en',
            ];

            if ($countryCode) {
                $params['components'] = "country:{$countryCode}";
            }

            $response = $this->client->request('GET', $this->apiUrl, [
                'query' => $params,
                'timeout' => 10,
            ]);

            $data = json_decode($response->getContent(), true);

            $this->logger->info('Google Places Restaurant Name Search Response', [
                'input' => $namePrefix,
                'country_code' => $countryCode,
                'http_status' => $response->getStatusCode(),
                'api_status' => $data['status'] ?? 'UNKNOWN',
                'full_response' => $data,
                'predictions_count' => count($data['predictions'] ?? [])
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Google Places API HTTP Error (restaurant name search)', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $data
                ]);
                return [];
            }

            if (!isset($data['status']) || $data['status'] !== 'OK') {
                $this->logger->warning('Google Places API returned non-OK status (restaurant name search)', [
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                    'full_response' => $data
                ]);
                return [];
            }

            $suggestions = [];
            foreach ($data['predictions'] ?? [] as $prediction) {
                // Only include if type contains 'restaurant' or 'cafe'
                $types = $prediction['types'] ?? [];
                if (in_array('restaurant', $types) || in_array('cafe', $types) || in_array('food', $types)) {
                    $placeDetails = $this->getPlaceDetails($prediction['place_id']);
                    $suggestion = [
                        'place_id' => $prediction['place_id'],
                        'description' => $prediction['description'],
                        'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                        'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? '',
                        'types' => $types,
                        'matched_substrings' => $prediction['matched_substrings'] ?? []
                    ];
                    if ($placeDetails && isset($placeDetails['geometry']['location'])) {
                        $suggestion['lat'] = $placeDetails['geometry']['location']['lat'];
                        $suggestion['lng'] = $placeDetails['geometry']['location']['lng'];
                    }
                    if ($placeDetails && isset($placeDetails['address_components'])) {
                        $suggestion['province'] = $this->extractProvinceFromAddressComponents($placeDetails['address_components']);
                    }
                    $suggestions[] = $suggestion;
                }
            }

            return $suggestions;
        } catch (\Exception $e) {
            $this->logger->error('Google Places API Error (restaurant name search)', [
                'message' => $e->getMessage(),
                'input' => $namePrefix,
                'country_code' => $countryCode
            ]);
            return [];
        }
    }

    /**
     * Search for restaurants by partial name near a location
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param float $radiusKm Radius in kilometers
     * @param string $namePrefix Partial or full restaurant name
     * @param string|null $countryCode Optional country code to restrict results
     * @return array Array of matching restaurants
     */
    public function searchNearbyRestaurantsByName(float $lat, float $lng, float $radiusKm, string $namePrefix, ?string $countryCode = null): array
    {
        if (strlen(trim($namePrefix)) < 2) {
            throw new \InvalidArgumentException('Restaurant name prefix must be at least 2 characters long');
        }
        $radiusMeters = (int)($radiusKm * 1000);
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
        $params = [
            'location' => $lat . ',' . $lng,
            'radius' => $radiusMeters,
            'type' => 'restaurant',
            'name' => trim($namePrefix),
            'key' => $this->apiKey,
        ];
        if ($countryCode) {
            $params['components'] = "country:{$countryCode}";
        }
        try {
            $response = $this->client->request('GET', $url, [
                'query' => $params,
                'timeout' => 10,
            ]);
            $data = json_decode($response->getContent(), true);
            $this->logger->info('Google Places Nearby Restaurant Name Search Response', [
                'lat' => $lat,
                'lng' => $lng,
                'radius_m' => $radiusMeters,
                'namePrefix' => $namePrefix,
                'country_code' => $countryCode,
                'http_status' => $response->getStatusCode(),
                'api_status' => $data['status'] ?? 'UNKNOWN',
                'results_count' => count($data['results'] ?? [])
            ]);
            if ($response->getStatusCode() !== 200 || ($data['status'] ?? '') !== 'OK') {
                $this->logger->error('Google Places Nearby Restaurant Name Search API Error', [
                    'status_code' => $response->getStatusCode(),
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                    'response' => $data
                ]);
                return [];
            }
            return $data['results'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Google Places API Error (nearby restaurant name search)', [
                'message' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radiusKm,
                'namePrefix' => $namePrefix,
                'country_code' => $countryCode
            ]);
            return [];
        }
    }

    /**
     * Get the API key (for testing purposes)
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get the API URL (for testing purposes)
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Extract province/state from address components
     * 
     * @param array $addressComponents Array of address components from Google Places API
     * @return string|null Province/state name or null if not found
     */
    private function extractProvinceFromAddressComponents(array $addressComponents): ?string
    {
        foreach ($addressComponents as $component) {
            $types = $component['types'] ?? [];
            
            // Look for administrative_area_level_1 (province/state)
            if (in_array('administrative_area_level_1', $types)) {
                return $component['long_name'] ?? null;
            }
        }
        
        return null;
    }
} 