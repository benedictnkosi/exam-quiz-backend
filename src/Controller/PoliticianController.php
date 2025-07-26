<?php

namespace App\Controller;

use App\Service\ShadyMeterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

#[Route('/api/politicians')]
class PoliticianController extends AbstractController
{
    public function __construct(
        private readonly ShadyMeterService $shadyMeterService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * POST - Generate Politicians List
     * 
     * Generates a list of 20 politicians to watch in a specific country based on corruption allegations using OpenAI.
     */
    #[Route('', name: 'generate_politicians', methods: ['POST'])]
    public function generatePoliticians(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['country']) || empty($data['country'])) {
            return $this->json([
                'error' => 'Missing or invalid country',
                'details' => 'Country parameter is required'
            ], 400);
        }

        try {
            $result = $this->shadyMeterService->generatePoliticians($data['country']);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate politicians',
                'details' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * GET - Retrieve Politicians
     * 
     * Retrieves politicians from the database, excluding trending politicians.
     */
    #[Route('', name: 'get_politicians', methods: ['GET'])]
    public function getPoliticians(Request $request): JsonResponse
    {
        $country = $request->query->get('country');
        
        if (!$country || empty($country)) {
            return $this->json([
                'error' => 'Missing or invalid country',
                'details' => 'Country query parameter is required'
            ], 400);
        }

        try {
            $politicians = $this->shadyMeterService->getPoliticians($country);
            
            // Create serialization context with proper groups
            $context = SerializationContext::create()->setGroups(['politician:read']);
            $serializedData = $this->serializer->serialize($politicians, 'json', $context);
            $data = json_decode($serializedData, true);
            
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve politicians',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Generate Trending Politicians
     * 
     * Generates a list of 10 most trending politicians for corruption scandals in the current year using OpenAI with web search.
     */
    #[Route('/trending', name: 'generate_trending_politicians', methods: ['POST'])]
    public function generateTrendingPoliticians(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['country']) || empty($data['country'])) {
            return $this->json([
                'error' => 'Missing or invalid country',
                'details' => 'Country parameter is required'
            ], 400);
        }

        try {
            error_log("ShadyMeter Controller: Generating trending politicians for country: " . $data['country']);
            $result = $this->shadyMeterService->generateTrendingPoliticians($data['country']);
            error_log("ShadyMeter Controller: Generated " . count($result) . " trending politicians");
            return $this->json($result);
        } catch (\Exception $e) {
            error_log("ShadyMeter Controller: Error generating trending politicians: " . $e->getMessage());
            return $this->json([
                'error' => 'Failed to generate trending politicians',
                'details' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * GET - Retrieve Trending Politicians
     * 
     * Retrieves trending politicians from the database.
     */
    #[Route('/trending', name: 'get_trending_politicians', methods: ['GET'])]
    public function getTrendingPoliticians(Request $request): JsonResponse
    {
        try {
            $politicians = $this->shadyMeterService->getTrendingPoliticians($request);
            
            // Create serialization context with proper groups
            $context = SerializationContext::create()->setGroups(['politician:read']);
            $serializedData = $this->serializer->serialize($politicians, 'json', $context);
            $data = json_decode($serializedData, true);
            
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve trending politicians',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Retrieve Politician by ID
     * 
     * Retrieves a specific politician from the database by their ID.
     */
    #[Route('/{id}', name: 'get_politician_by_id', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getPoliticianById(int $id): JsonResponse
    {
        try {
            $politician = $this->shadyMeterService->getPoliticianById($id);
            
            if (!$politician) {
                return $this->json([
                    'error' => 'Politician not found',
                    'details' => 'No politician found with ID: ' . $id
                ], 404);
            }
            
            return $this->json($politician);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve politician',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 