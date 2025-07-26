<?php

namespace App\Controller;

use App\Service\ShadyMeterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

#[Route('/api/news')]
class NewsController extends AbstractController
{
    public function __construct(
        private readonly ShadyMeterService $shadyMeterService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * POST - Fetch Trending Corruption News
     * 
     * Fetches the top 3 most significant corruption-related news stories for a specific country.
     * Uses OpenAI with web search to get current news and caches results for the day.
     */
    #[Route('', name: 'fetch_news', methods: ['POST'])]
    public function fetchNews(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['country']) || empty($data['country'])) {
            return $this->json([
                'error' => 'Missing or invalid country',
                'details' => 'Country parameter is required'
            ], 400);
        }

        try {
            $result = $this->shadyMeterService->generateNews($data['country']);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate news',
                'details' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * GET - Retrieve News
     * 
     * Retrieves news from the database with optional filtering.
     */
    #[Route('', name: 'get_news', methods: ['GET'])]
    public function getNews(Request $request): JsonResponse
    {
        try {
            $news = $this->shadyMeterService->getNews($request);
            
            // Create serialization context with proper groups
            $context = SerializationContext::create()->setGroups(['news:read']);
            $serializedData = $this->serializer->serialize($news, 'json', $context);
            $data = json_decode($serializedData, true);
            
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve news',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 