<?php

namespace App\Controller;

use App\Service\ShadyMeterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

#[Route('/api/politicians/scandals')]
class PoliticianScandalController extends AbstractController
{
    public function __construct(
        private readonly ShadyMeterService $shadyMeterService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * POST - Generate Politician Scandals
     * 
     * Generates a list of corruption-related scandals for a specific politician using OpenAI with web search.
     */
    #[Route('', name: 'generate_politician_scandals', methods: ['POST'])]
    public function generatePoliticianScandals(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['politician']) || empty($data['politician'])) {
            return $this->json([
                'error' => 'Missing or invalid politician',
                'details' => 'Politician parameter is required'
            ], 400);
        }

        if (!isset($data['country']) || empty($data['country'])) {
            return $this->json([
                'error' => 'Missing or invalid country',
                'details' => 'Country parameter is required'
            ], 400);
        }

        try {
            $result = $this->shadyMeterService->generatePoliticianScandals($data['politician'], $data['country']);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate politician scandals',
                'details' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * GET - Retrieve Politician Scandals
     * 
     * Retrieves scandals for a specific politician from the database.
     */
    #[Route('', name: 'get_politician_scandals', methods: ['GET'])]
    public function getPoliticianScandals(Request $request): JsonResponse
    {
        $politician = $request->query->get('politician');
        
        if (!$politician || empty($politician)) {
            return $this->json([
                'error' => 'Missing politician query parameter',
                'details' => 'Politician query parameter is required'
            ], 400);
        }

        try {
            $scandals = $this->shadyMeterService->getPoliticianScandals($politician);
            
            // Create serialization context with proper groups
            $context = SerializationContext::create()->setGroups(['scandal:read']);
            $serializedData = $this->serializer->serialize($scandals, 'json', $context);
            $data = json_decode($serializedData, true);
            
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve politician scandals',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Generate Scandal Article
     * 
     * Generates a detailed journalistic article (300-500 words) about a specific scandal using OpenAI.
     * Returns article text, timeline of key events, and full names of involved persons.
     * If an article already exists, returns the cached version without calling AI.
     */
    #[Route('/article', name: 'generate_scandal_article', methods: ['POST'])]
    public function generateScandalArticle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['politician']) || empty($data['politician'])) {
            return $this->json([
                'error' => 'Missing required fields',
                'details' => 'Politician parameter is required'
            ], 400);
        }

        if (!isset($data['country']) || empty($data['country'])) {
            return $this->json([
                'error' => 'Missing required fields',
                'details' => 'Country parameter is required'
            ], 400);
        }

        if (!isset($data['scandal']) || !is_array($data['scandal'])) {
            return $this->json([
                'error' => 'Missing required fields',
                'details' => 'Scandal object is required'
            ], 400);
        }

        $requiredScandalFields = ['title', 'year', 'description'];
        foreach ($requiredScandalFields as $field) {
            if (!isset($data['scandal'][$field]) || empty($data['scandal'][$field])) {
                return $this->json([
                    'error' => 'Missing required fields',
                    'details' => "Scandal {$field} is required"
                ], 400);
            }
        }

        try {
            $result = $this->shadyMeterService->generateScandalArticle(
                $data['politician'],
                $data['country'],
                $data['scandal']
            );
            
            // Check if this was a cached response by looking for existing article
            $scandals = $this->shadyMeterService->getPoliticianScandals($data['politician']);
            $isCached = false;
            
            foreach ($scandals as $scandalDoc) {
                // Use entity getter methods instead of array access
                if ($scandalDoc->getCountry() === $data['country']) {
                    $scandalsArray = $scandalDoc->getScandals();
                    foreach ($scandalsArray as $scandal) {
                        if ($scandal['title'] === $data['scandal']['title'] && 
                            (string)$scandal['year'] === (string)$data['scandal']['year']) {
                            $isCached = isset($scandal['article']) && !empty($scandal['article']);
                            break 2;
                        }
                    }
                }
            }
            
            // Add cache status to response
            $result['cached'] = $isCached;
            
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate scandal article',
                'details' => $e->getMessage()
            ], 502);
        }
    }
} 