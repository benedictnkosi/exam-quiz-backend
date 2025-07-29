<?php

namespace App\Controller;

use App\Repository\PostedTrendingNewsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

#[Route('/api/posted-trending-news')]
class PostedTrendingNewsController extends AbstractController
{
    public function __construct(
        private readonly PostedTrendingNewsRepository $postedTrendingNewsRepository,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * GET - Get recently posted trending news
     * 
     * Retrieves recently posted trending news with optional filtering by scope and date range.
     */
    #[Route('', name: 'get_posted_trending_news', methods: ['GET'])]
    public function getPostedTrendingNews(Request $request): JsonResponse
    {
        try {
            $scope = $request->query->get('scope', 'global');
            $limit = (int) $request->query->get('limit', 10);
            $days = (int) $request->query->get('days', 30);

            $startDate = new \DateTime();
            $startDate->modify("-{$days} days");
            $endDate = new \DateTime();

            $postedNews = $this->postedTrendingNewsRepository->findByScopeAndDateRange($scope, $startDate, $endDate);
            
            // Limit results
            $postedNews = array_slice($postedNews, 0, $limit);

            // Create serialization context with proper groups
            $context = SerializationContext::create()->setGroups(['posted_trending:read']);
            $serializedData = $this->serializer->serialize($postedNews, 'json', $context);
            $data = json_decode($serializedData, true);
            
            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data),
                'scope' => $scope,
                'dateRange' => [
                    'start' => $startDate->format('c'),
                    'end' => $endDate->format('c')
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve posted trending news',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Get recently posted politicians
     * 
     * Retrieves list of politicians who have been posted recently to avoid duplicates.
     */
    #[Route('/politicians', name: 'get_recently_posted_politicians', methods: ['GET'])]
    public function getRecentlyPostedPoliticians(Request $request): JsonResponse
    {
        try {
            $scope = $request->query->get('scope', 'global');
            
            $politicians = $this->postedTrendingNewsRepository->getRecentlyPostedPoliticians($scope);
            
            // Extract just the politician names
            $politicianNames = array_map(function($item) {
                return $item['politicianName'];
            }, $politicians);
            
            return $this->json([
                'success' => true,
                'politicians' => $politicianNames,
                'total' => count($politicianNames),
                'scope' => $scope
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve recently posted politicians',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Check if politician has been posted recently
     * 
     * Checks if a specific politician has been posted in the last 30 days.
     */
    #[Route('/check-politician', name: 'check_politician_posted', methods: ['GET'])]
    public function checkPoliticianPosted(Request $request): JsonResponse
    {
        try {
            $politicianName = $request->query->get('politician');
            $scope = $request->query->get('scope', 'global');
            
            if (empty($politicianName)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Politician name is required'
                ], 400);
            }
            
            $hasBeenPosted = $this->postedTrendingNewsRepository->hasPoliticianBeenPostedRecently($politicianName, $scope);
            
            return $this->json([
                'success' => true,
                'politicianName' => $politicianName,
                'scope' => $scope,
                'hasBeenPostedRecently' => $hasBeenPosted,
                'message' => $hasBeenPosted 
                    ? "Politician {$politicianName} has been posted recently in scope {$scope}"
                    : "Politician {$politicianName} has not been posted recently in scope {$scope}"
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to check politician posting status',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Get posting statistics
     * 
     * Retrieves statistics about posted trending news.
     */
    #[Route('/statistics', name: 'get_posting_statistics', methods: ['GET'])]
    public function getPostingStatistics(Request $request): JsonResponse
    {
        try {
            $scope = $request->query->get('scope', 'global');
            $days = (int) $request->query->get('days', 30);
            
            $startDate = new \DateTime();
            $startDate->modify("-{$days} days");
            $endDate = new \DateTime();

            $postedNews = $this->postedTrendingNewsRepository->findByScopeAndDateRange($scope, $startDate, $endDate);
            
            // Calculate statistics
            $totalPosts = count($postedNews);
            $politiciansPosted = [];
            $successfulPosts = 0;
            
            foreach ($postedNews as $post) {
                if ($post->getPoliticianName()) {
                    $politiciansPosted[] = $post->getPoliticianName();
                }
                if ($post->getTweetId()) {
                    $successfulPosts++;
                }
            }
            
            $uniquePoliticians = array_unique($politiciansPosted);
            
            return $this->json([
                'success' => true,
                'statistics' => [
                    'totalPosts' => $totalPosts,
                    'successfulPosts' => $successfulPosts,
                    'failedPosts' => $totalPosts - $successfulPosts,
                    'uniquePoliticiansPosted' => count($uniquePoliticians),
                    'politiciansList' => array_values($uniquePoliticians),
                    'successRate' => $totalPosts > 0 ? round(($successfulPosts / $totalPosts) * 100, 2) : 0
                ],
                'scope' => $scope,
                'dateRange' => [
                    'start' => $startDate->format('c'),
                    'end' => $endDate->format('c'),
                    'days' => $days
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve posting statistics',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 