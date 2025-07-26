<?php

namespace App\Controller;

use App\Service\TwitterNewsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/twitter-news')]
class TwitterNewsController extends AbstractController
{
    public function __construct(
        private readonly TwitterNewsService $twitterNewsService
    ) {
    }

    /**
     * POST - Generate Twitter summaries from trending news
     * 
     * Generates Twitter-friendly summaries (max 280 characters) from trending news
     * without posting them to Twitter.
     */
    #[Route('/generate-summaries', name: 'generate_twitter_summaries', methods: ['POST'])]
    public function generateSummaries(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $useExisting = $data['useExisting'] ?? false;
        $scope = $data['scope'] ?? 'global';

        try {
            if ($useExisting) {
                $result = $this->twitterNewsService->getLatestNews($scope);
            } else {
                $result = $this->twitterNewsService->generateTrendingNewsForTwitter($scope);
            }

            if (!$result['success']) {
                return $this->json([
                    'error' => 'Failed to generate Twitter summaries',
                    'details' => $result['error']
                ], 502);
            }

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate Twitter summaries',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Generate fresh Twitter summaries from trending news
     * 
     * Generates fresh Twitter-friendly summaries (max 280 characters) from trending news
     * using AI without saving to database or using cached news.
     */
    #[Route('/generate-fresh-summaries', name: 'generate_fresh_twitter_summaries', methods: ['POST'])]
    public function generateFreshSummaries(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $scope = $data['scope'] ?? 'global';

        try {
            $result = $this->twitterNewsService->generateTrendingNewsForTwitter($scope);

            if (!$result['success']) {
                return $this->json([
                    'error' => 'Failed to generate fresh Twitter summaries',
                    'details' => $result['error']
                ], 502);
            }

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate fresh Twitter summaries',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Post a single Twitter summary
     * 
     * Posts a single Twitter summary to Twitter.
     */
    #[Route('/post-summary', name: 'post_twitter_summary', methods: ['POST'])]
    public function postSummary(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['summary']) || empty($data['summary'])) {
            return $this->json([
                'error' => 'Missing or empty summary',
                'details' => 'The "summary" field is required and must not be empty.'
            ], 400);
        }

        if (strlen($data['summary']) > 280) {
            return $this->json([
                'error' => 'Summary too long',
                'details' => 'Summary exceeds Twitter character limit (280 characters).'
            ], 400);
        }

        try {
            $result = $this->twitterNewsService->postTwitterSummary($data['summary']);

            if (!$result['success']) {
                return $this->json([
                    'error' => 'Failed to post tweet',
                    'details' => $result['error']
                ], 502);
            }

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to post tweet',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Generate and post all Twitter summaries
     * 
     * Generates Twitter summaries from trending news and posts them all to Twitter.
     */
    #[Route('/generate-and-post', name: 'generate_and_post_twitter_summaries', methods: ['POST'])]
    public function generateAndPost(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $useExisting = $data['useExisting'] ?? false;
        $scope = $data['scope'] ?? 'global';

        try {
            if ($useExisting) {
                // Get existing news first, then post summaries
                $newsResult = $this->twitterNewsService->getLatestNews($scope);
                
                if (!$newsResult['success']) {
                    return $this->json([
                        'error' => 'Failed to get existing news',
                        'details' => $newsResult['error']
                    ], 502);
                }

                $postedTweets = [];
                $failedTweets = [];

                foreach ($newsResult['summaries'] as $summaryData) {
                    $postResult = $this->twitterNewsService->postTwitterSummary($summaryData['twitterSummary']);
                    
                    if ($postResult['success']) {
                        $postedTweets[] = $postResult;
                    } else {
                        $failedTweets[] = [
                            'summary' => $summaryData['twitterSummary'],
                            'error' => $postResult['error']
                        ];
                    }

                    // Add a small delay between tweets to avoid rate limiting
                    sleep(2);
                }

                $result = [
                    'success' => true,
                    'postedTweets' => $postedTweets,
                    'failedTweets' => $failedTweets,
                    'totalGenerated' => count($newsResult['summaries']),
                    'totalPosted' => count($postedTweets),
                    'totalFailed' => count($failedTweets)
                ];
            } else {
                $result = $this->twitterNewsService->generateAndPostAllSummaries($scope);
            }

            if (!$result['success']) {
                return $this->json([
                    'error' => 'Failed to generate and post tweets',
                    'details' => $result['error']
                ], 502);
            }

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate and post tweets',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET - Get latest global news summaries
     * 
     * Retrieves the latest global news and generates Twitter summaries without posting.
     */
    #[Route('/latest-summaries', name: 'get_latest_twitter_summaries', methods: ['GET'])]
    public function getLatestSummaries(): JsonResponse
    {
        try {
            $result = $this->twitterNewsService->getLatestGlobalNews();

            if (!$result['success']) {
                return $this->json([
                    'error' => 'Failed to get latest summaries',
                    'details' => $result['error']
                ], 404);
            }

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to get latest summaries',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST - Test Twitter connection
     * 
     * Tests the Twitter API connection by posting a test tweet.
     */
    #[Route('/test-connection', name: 'test_twitter_connection', methods: ['POST'])]
    public function testConnection(): JsonResponse
    {
        try {
            $testMessage = '🧪 Test tweet from ShadyMeter API - ' . date('Y-m-d H:i:s') . ' #TestTweet';
            
            $result = $this->twitterNewsService->postTwitterSummary($testMessage);

            if (!$result['success']) {
                return $this->json([
                    'error' => 'Twitter connection test failed',
                    'details' => $result['error']
                ], 502);
            }

            return $this->json([
                'success' => true,
                'message' => 'Twitter connection test successful',
                'tweetId' => $result['tweetId'],
                'testMessage' => $testMessage
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Twitter connection test failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 