<?php

namespace App\Controller;

use App\Service\ShadyMeterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

#[Route('/api/global-trending-news')]
class GlobalTrendingNewsController extends AbstractController
{
    public function __construct(
        private readonly ShadyMeterService $shadyMeterService,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * POST - Generate Global Trending News
     * 
     * Generates the top 3 most significant global corruption-related news stories for the current week.
     * Uses OpenAI with web search to get current global news and caches results for the week.
     */
    #[Route('', name: 'generate_global_trending_news', methods: ['POST'])]
    public function generateGlobalTrendingNews(): JsonResponse
    {
        try {
            $result = $this->shadyMeterService->generateGlobalTrendingNews();
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate global trending news',
                'details' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * GET - Retrieve Global Trending News
     * 
     * Retrieves global trending news from the database with optional filtering.
     */
    #[Route('', name: 'get_global_trending_news', methods: ['GET'])]
    public function getGlobalTrendingNews(Request $request): JsonResponse
    {
        try {
            $news = $this->shadyMeterService->getGlobalTrendingNews($request);
            
            // Create serialization context with proper groups
            $context = SerializationContext::create()->setGroups(['news:read']);
            $serializedData = $this->serializer->serialize($news, 'json', $context);
            $data = json_decode($serializedData, true);
            
            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve global trending news',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Generate Full Story
     * 
     * Generates a detailed, comprehensive news article for a specific story within global trending news.
     * Uses OpenAI with web search to expand the story and caches the result in the news JSON.
     */
    #[Route('/{newsId}/story/{storyKey}', name: 'generate_full_story', methods: ['POST'])]
    public function generateFullStory(int $newsId, string $storyKey): JsonResponse
    {
        try {
            $result = $this->shadyMeterService->generateFullStory($newsId, $storyKey);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate full story',
                'details' => $e->getMessage()
            ], 502);
        }
    }
} 