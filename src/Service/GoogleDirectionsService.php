<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GoogleDirectionsService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $apiUrl = 'https://maps.googleapis.com/maps/api/directions/json';

    public function __construct(
        string $googleDirectionsApiKey,
        private readonly LoggerInterface $logger
    ) {
        $this->client = \Symfony\Component\HttpClient\HttpClient::create();
        $this->apiKey = $googleDirectionsApiKey;
    }

    /**
     * Get route coordinates between two points
     * 
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param float $destinationLat Destination latitude
     * @param float $destinationLng Destination longitude
     * @param int $numPoints Number of coordinate points to return (default: 5)
     * @return array|null Array of coordinate points or null if error
     */
    public function getRouteCoordinates(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        int $numPoints = 20
    ): ?array {
        try {
            $params = [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destinationLat},{$destinationLng}",
                'key' => $this->apiKey,
                'mode' => 'driving', // Default to driving mode
                'units' => 'metric'
            ];

            $response = $this->client->request('GET', $this->apiUrl, [
                'query' => $params,
                'timeout' => 15, // 15 second timeout for directions
            ]);

            $data = json_decode($response->getContent(), true);

            // Log the full response for debugging
            $this->logger->info('Google Directions API Response', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destinationLat},{$destinationLng}",
                'http_status' => $response->getStatusCode(),
                'api_status' => $data['status'] ?? 'UNKNOWN',
                'full_response' => $data
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Google Directions API HTTP Error', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $data
                ]);
                return null;
            }

            if (!isset($data['status']) || $data['status'] !== 'OK') {
                $this->logger->warning('Google Directions API returned non-OK status', [
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'error_message' => $data['error_message'] ?? 'No error message provided',
                    'full_response' => $data
                ]);
                return null;
            }

            // Extract route coordinates
            $routeCoordinates = $this->extractRouteCoordinates($data, $numPoints);

            if ($routeCoordinates) {
                $this->logger->info('Route coordinates extracted successfully', [
                    'origin' => "{$originLat},{$originLng}",
                    'destination' => "{$destinationLat},{$destinationLng}",
                    'points_count' => count($routeCoordinates)
                ]);
            }

            return $routeCoordinates;

        } catch (\Exception $e) {
            $this->logger->error('Google Directions API Error', [
                'message' => $e->getMessage(),
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destinationLat},{$destinationLng}"
            ]);
            
            return null;
        }
    }

    /**
     * Extract coordinate points from Google Directions response
     */
    private function extractRouteCoordinates(array $data, int $numPoints): ?array
    {
        if (empty($data['routes'])) {
            return null;
        }

        $route = $data['routes'][0];
        if (empty($route['legs'])) {
            return null;
        }

        $leg = $route['legs'][0];
        if (empty($leg['steps'])) {
            return null;
        }

        // Collect all coordinate points from route steps
        $allCoordinates = [];
        
        // Add origin point
        $allCoordinates[] = [
            'lat' => (float) $leg['start_location']['lat'],
            'lng' => (float) $leg['start_location']['lng']
        ];

        // Add points from each step
        foreach ($leg['steps'] as $step) {
            if (isset($step['polyline']['points'])) {
                $decodedPoints = $this->decodePolyline($step['polyline']['points']);
                $allCoordinates = array_merge($allCoordinates, $decodedPoints);
            }
        }

        // Add destination point
        $allCoordinates[] = [
            'lat' => (float) $leg['end_location']['lat'],
            'lng' => (float) $leg['end_location']['lng']
        ];

        // Remove duplicates
        $uniqueCoordinates = [];
        foreach ($allCoordinates as $coord) {
            $key = $coord['lat'] . ',' . $coord['lng'];
            if (!isset($uniqueCoordinates[$key])) {
                $uniqueCoordinates[$key] = $coord;
            }
        }
        $uniqueCoordinates = array_values($uniqueCoordinates);

        // Log the total number of route coordinates before sampling
        $this->logger->info('Total route coordinates returned by Google', [
            'total_coordinates' => count($uniqueCoordinates)
        ]);

        // Sample evenly distributed points
        return $this->sampleCoordinates($uniqueCoordinates, $numPoints);
    }

    /**
     * Decode Google's polyline format
     */
    private function decodePolyline(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $coordinates[] = [
                'lat' => $lat / 100000,
                'lng' => $lng / 100000
            ];
        }

        return $coordinates;
    }

    /**
     * Sample evenly distributed coordinates from the route
     */
    private function sampleCoordinates(array $coordinates, int $numPoints): array
    {
        $count = count($coordinates);
        
        if ($count <= $numPoints) {
            return $coordinates;
        }

        $sampled = [];
        $step = ($count - 1) / ($numPoints - 1);

        for ($i = 0; $i < $numPoints; $i++) {
            $index = round($i * $step);
            $sampled[] = $coordinates[$index];
        }

        return $sampled;
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
} 