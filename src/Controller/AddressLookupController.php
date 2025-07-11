<?php

namespace App\Controller;

use App\Service\GooglePlacesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/address', name: 'address_')]
class AddressLookupController extends AbstractController
{
    public function __construct(
        private GooglePlacesService $googlePlacesService,
        private LoggerInterface $logger
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