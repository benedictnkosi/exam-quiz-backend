<?php

namespace App\Service;

use App\Repository\NewsRepository;
use App\Repository\PostedTrendingNewsRepository;
use App\Entity\PostedTrendingNews;
use Psr\Log\LoggerInterface;

class TwitterNewsService
{
    public function __construct(
        private readonly ShadyMeterService $shadyMeterService,
        private readonly TwitterService $twitterService,
        private readonly NewsRepository $newsRepository,
        private readonly PostedTrendingNewsRepository $postedTrendingNewsRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate Twitter summaries from global trending news
     */
    public function generateTwitterSummaries(): array
    {
        try {
            // Get global trending news
            $globalNews = $this->shadyMeterService->generateGlobalTrendingNews();
            
            if (!isset($globalNews['news']) || !is_array($globalNews['news'])) {
                throw new \Exception('No global news found or invalid format');
            }

            $summaries = [];
            foreach ($globalNews['news'] as $story) {
                $summary = $this->createTwitterSummary($story);
                if ($summary) {
                    $summaries[] = [
                        'originalStory' => $story,
                        'twitterSummary' => $summary,
                        'characterCount' => strlen($summary)
                    ];
                }
            }

            $this->logger->info('Generated ' . count($summaries) . ' Twitter summaries from global news');
            
            return [
                'success' => true,
                'summaries' => $summaries,
                'totalCount' => count($summaries)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error generating Twitter summaries: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate trending news specifically for Twitter with 280-character summaries
     * This method does NOT save to database and generates fresh content each time
     */
    public function generateTrendingNewsForTwitter(string $scope = 'global'): array
    {
        try {
            // Get current date range for the prompt (last 7 days)
            $currentDate = new \DateTime();
            $startDate = clone $currentDate;
            $startDate->modify('-1 days');
            
            $startDateStr = $startDate->format('F j, Y');
            $endDateStr = $currentDate->format('F j, Y');

            $scopeText = $this->getScopeDescription($scope);
            $input = "Provide the top 1 most significant {$scopeText} corruption-related news story between {$startDateStr} and {$endDateStr}. Focus on political corruption, embezzlement, bribery, and accountability issues. 

Return the response in this exact JSON format:
{
  \"twitterSummary\": \"Write a funny, light, and engaging Twitter summary (200-220 characters) with witty commentary, clever wordplay, or humorous observations about the situation. Make it entertaining while still being informative. Include an appropriate emoji at the start (🚨 for high impact, ⚠️ for medium, 📰 for low). End with hashtags #CorruptionNews #GlobalTrending. Do not include any URLs or links.\",
  \"politicianName\": \"Full name of the main politician involved (e.g., 'John Doe' or null if no specific politician)\",
  \"country\": \"Country where the corruption occurred\",
  \"impact\": \"high|medium|low\"
}

Only include real, documented events - do not invent stories. Return ONLY the JSON response.";

            $data = [
                'model' => 'gpt-4.1',
                'tools' => [
                    [
                        'type' => 'web_search_preview'
                    ]
                ],
                'input' => $input
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openAI.com/v1/responses',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $_ENV['OPENAI_API_KEY']
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception('cURL error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception('OpenAI API error: HTTP ' . $httpCode . ' - ' . $response);
            }

            $responseData = json_decode($response, true);
            if (!$responseData) {
                throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
            }

            if (!isset($responseData['output']) || !is_array($responseData['output'])) {
                throw new \Exception('Response missing output array: ' . json_encode($responseData));
            }

            // Extract the text content from the output array
            $content = null;
            foreach ($responseData['output'] as $outputItem) {
                if (isset($outputItem['type']) && $outputItem['type'] === 'message' && isset($outputItem['content'])) {
                    foreach ($outputItem['content'] as $contentItem) {
                        if (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                            $content = $contentItem['text'];
                            break 2;
                        }
                    }
                }
            }

            if (empty($content)) {
                throw new \Exception('No text content found in response output: ' . json_encode($responseData['output']));
            }
            
            // Parse the JSON response
            $parsedData = $this->parseAIResponse($content);
            
            if (!$parsedData) {
                throw new \Exception('Failed to parse AI response as JSON: ' . $content);
            }
            
            // Extract data from parsed response
            $twitterSummary = $parsedData['twitterSummary'] ?? '';
            $politicianName = $parsedData['politicianName'] ?? null;
            $country = $parsedData['country'] ?? '';
            $impact = $parsedData['impact'] ?? 'medium';
            
            // Clean and format the Twitter summary
            $twitterSummary = $this->cleanAndFormatTwitterSummary($twitterSummary);

            $this->logger->info('Generated Twitter summary from fresh ' . $scope . ' news');
            
            return [
                'success' => true,
                'summaries' => [
                    [
                        'twitterSummary' => $twitterSummary,
                        'characterCount' => strlen($twitterSummary),
                        'politicianName' => $politicianName,
                        'country' => $country,
                        'impact' => $impact,
                        'originalStory' => [
                            'politicianName' => $politicianName,
                            'country' => $country,
                            'impact' => $impact
                        ]
                    ]
                ],
                'totalCount' => 1,
                'generatedAt' => $currentDate->format('c'),
                'scope' => $scope,
                'dateRange' => [
                    'start' => $startDateStr,
                    'end' => $endDateStr
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error generating Twitter global news: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Post a single Twitter summary
     */
    public function postTwitterSummary(string $summary, string $scope = 'global', ?array $originalStory = null): array
    {
        try {
            if (strlen($summary) > 280) {
                throw new \Exception('Summary exceeds Twitter character limit (280 characters)');
            }

            // Get politician name from original story or extract from summary as fallback
            $politicianName = $originalStory['politicianName'] ?? null;
            
            // Check if this politician has been posted recently
            if ($politicianName && $this->postedTrendingNewsRepository->hasPoliticianBeenPostedRecently($politicianName, $scope)) {
                $this->logger->info("Politician {$politicianName} has been posted recently in scope {$scope}, skipping");
                return [
                    'success' => false,
                    'error' => "Politician {$politicianName} has been posted recently in scope {$scope}",
                    'politicianName' => $politicianName,
                    'reason' => 'politician_already_posted'
                ];
            }

            // Check if this exact summary has been posted recently
            if ($this->postedTrendingNewsRepository->hasSummaryBeenPostedRecently($summary, $scope)) {
                $this->logger->info("Summary has been posted recently in scope {$scope}, skipping");
                return [
                    'success' => false,
                    'error' => 'Summary has been posted recently',
                    'reason' => 'summary_already_posted'
                ];
            }

            $result = $this->twitterService->postTweet($summary);
            
            if (isset($result['error'])) {
                $this->logger->error('Twitter API error: ' . json_encode($result));
                return [
                    'success' => false,
                    'error' => $result['error'],
                    'details' => $result['details'] ?? null
                ];
            }

            // Track the posted trending news
            $postedTrendingNews = new PostedTrendingNews();
            $postedTrendingNews->setScope($scope);
            $postedTrendingNews->setPoliticianName($politicianName);
            $postedTrendingNews->setTwitterSummary($summary);
            $postedTrendingNews->setTweetId($result['data']['id'] ?? null);
            $postedTrendingNews->setCharacterCount(strlen($summary));
            $postedTrendingNews->setOriginalStory($originalStory);
            
            $this->postedTrendingNewsRepository->save($postedTrendingNews, true);

            $this->logger->info('Successfully posted tweet: ' . substr($summary, 0, 50) . '...');
            
            return [
                'success' => true,
                'tweetId' => $result['data']['id'] ?? null,
                'summary' => $summary,
                'characterCount' => strlen($summary),
                'politicianName' => $politicianName,
                'trackingId' => $postedTrendingNews->getId()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error posting Twitter summary: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate and post all Twitter summaries
     */
    public function generateAndPostAllSummaries(string $scope = 'global'): array
    {
        try {
            // Generate summaries using the new method
            $summaryResult = $this->generateTrendingNewsForTwitter($scope);
            
            if (!$summaryResult['success']) {
                return $summaryResult;
            }

            $postedTweets = [];
            $failedTweets = [];

            foreach ($summaryResult['summaries'] as $summaryData) {
                $postResult = $this->postTwitterSummary(
                    $summaryData['twitterSummary'], 
                    $scope, 
                    $summaryData['originalStory'] ?? null
                );
                
                if ($postResult['success']) {
                    $postedTweets[] = $postResult;
                } else {
                    $failedTweets[] = [
                        'summary' => $summaryData['twitterSummary'],
                        'error' => $postResult['error'],
                        'reason' => $postResult['reason'] ?? 'unknown'
                    ];
                }

                // Add a small delay between tweets to avoid rate limiting
                sleep(2);
            }

            $this->logger->info('Posted ' . count($postedTweets) . ' tweets, failed: ' . count($failedTweets));

            return [
                'success' => true,
                'postedTweets' => $postedTweets,
                'failedTweets' => $failedTweets,
                'totalGenerated' => count($summaryResult['summaries']),
                'totalPosted' => count($postedTweets),
                'totalFailed' => count($failedTweets)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in generateAndPostAllSummaries: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a Twitter-friendly summary (250-280 characters)
     */
    private function createTwitterSummary(array $story): ?string
    {
        $title = $story['title'] ?? '';
        $summary = $story['summary'] ?? '';
        $impact = $story['impact'] ?? 'medium';
        $source = $story['source'] ?? '';

        // Clean source - remove any URLs or links
        $source = $this->cleanSource($source);

        // Create a concise summary starting with the title
        $baseSummary = $title;
        
        // Add impact indicator
        $impactEmoji = $this->getImpactEmoji($impact);
        $baseSummary = $impactEmoji . ' ' . $baseSummary;

        // Add source if available and there's space (leave room for hashtags)
        $hashtags = ' #CorruptionNews #GlobalTrending';
        $maxLengthForContent = 280 - strlen($hashtags);
        
        if ($source && strlen($baseSummary) < ($maxLengthForContent - 10)) {
            $baseSummary .= ' | ' . $source;
        }

        // Add hashtags
        $baseSummary .= $hashtags;

        // Ensure the summary is between 250-280 characters
        $baseSummary = $this->trimToTwitterLimit($baseSummary);

        return $baseSummary;
    }

    /**
     * Get emoji based on impact level
     */
    private function getImpactEmoji(string $impact): string
    {
        return match (strtolower($impact)) {
            'high' => '🚨',
            'medium' => '⚠️',
            'low' => '📰',
            default => '📰'
        };
    }

    /**
     * Clean source text by removing URLs and links
     */
    private function cleanSource(string $source): string
    {
        // Remove URLs
        $source = preg_replace('/https?:\/\/[^\s]+/', '', $source);
        // Remove common URL patterns
        $source = preg_replace('/www\.[^\s]+/', '', $source);
        // Remove markdown links
        $source = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $source);
        // Clean up extra spaces
        $source = preg_replace('/\s+/', ' ', $source);
        // Remove any remaining brackets or parentheses that might contain URLs
        $source = preg_replace('/\([^)]*\)/', '', $source);
        
        return trim($source);
    }

    /**
     * Clean and format the Twitter summary from AI response
     */
    private function cleanAndFormatTwitterSummary(string $content): string
    {
        // Clean the content
        $summary = trim($content);
        
        // Remove any URLs or links more aggressively
        $summary = preg_replace('/https?:\/\/[^\s\)]+/', '', $summary);
        $summary = preg_replace('/www\.[^\s\)]+/', '', $summary);
        $summary = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $summary);
        $summary = preg_replace('/\([^)]*\)/', '', $summary); // Remove any remaining parentheses
        $summary = preg_replace('/\[[^\]]*\]/', '', $summary); // Remove any remaining brackets
        
        // Clean up extra spaces and newlines
        $summary = preg_replace('/\s+/', ' ', $summary);
        $summary = trim($summary);
        
        // Ensure it starts with an emoji
        if (!preg_match('/^[🚨⚠️📰]/', $summary)) {
            $summary = '🚨 ' . $summary;
        }
        
        // Ensure it ends with hashtags
        if (!str_contains($summary, '#CorruptionNews')) {
            $summary .= ' #CorruptionNews #GlobalTrending';
        }
        
        // Ensure the summary fits within Twitter limit while reserving space for the URL
        $summary = $this->trimSummaryForTwitter($summary);
        
        // Add the URL (this will always fit since we reserved space for it)
        $summary .= " Track politicians on https://corruptionbot.com";
        
        return $summary;
    }

    /**
     * Get scope description for AI prompt
     */
    private function getScopeDescription(string $scope): string
    {
        return match (strtolower($scope)) {
            'global' => 'global corruption-related news story from around the world',
            'africa' => 'African corruption-related news story',
            'usa', 'us', 'america' => 'US corruption-related news story',
            'north america', 'northamerica' => 'North American corruption-related news story',
            'europe' => 'European corruption-related news story',
            'asia' => 'Asian corruption-related news story',
            'latin america', 'latinamerica' => 'Latin American corruption-related news story',
            'middle east', 'middleeast' => 'Middle Eastern corruption-related news story',
            'south africa', 'southafrica' => 'South African corruption-related news story',
            'nigeria' => 'Nigerian corruption-related news story',
            'kenya' => 'Kenyan corruption-related news story',
            'ghana' => 'Ghanaian corruption-related news story',
            'uk' => 'UK corruption-related news story',
            'france' => 'French corruption-related news story',
            'germany' => 'German corruption-related news story',
            'china' => 'Chinese corruption-related news story',
            'india' => 'Indian corruption-related news story',
            default => $scope . ' corruption-related news story'
        };
    }

    /**
     * Clean OpenAI response by extracting JSON from code blocks
     */
    private function cleanOpenAIResponse(string $raw): string
    {
        // Extract JSON from a code block if present
        if (preg_match('/```json\s*([\s\S]*?)```/i', $raw, $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/```\s*([\s\S]*?)```/i', $raw, $matches)) {
            return trim($matches[1]);
        }
        
        return trim($raw);
    }

    /**
     * Get the latest news for a specific scope without generating new ones
     */
    public function getLatestNews(string $scope = 'global'): array
    {
        try {
            $news = $this->newsRepository->findByCountryAndLast24Hours($scope);
            
            if (!$news) {
                return [
                    'success' => false,
                    'error' => "No recent {$scope} news found"
                ];
            }

            $newsArray = $news->getNews();
            $summaries = [];

            foreach ($newsArray as $story) {
                $summary = $this->createTwitterSummary($story);
                if ($summary) {
                    $summaries[] = [
                        'originalStory' => $story,
                        'twitterSummary' => $summary,
                        'characterCount' => strlen($summary)
                    ];
                }
            }

            return [
                'success' => true,
                'summaries' => $summaries,
                'newsId' => $news->getId(),
                'scope' => $scope,
                'createdAt' => $news->getCreatedAt()->format('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting latest global news: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Ensure a string is trimmed to 280 characters (with ellipsis if needed)
     */
    private function trimToTwitterLimit(string $text): string
    {
        if (strlen($text) > 280) {
            return substr($text, 0, 277) . '...';
        }
        return $text;
    }

    /**
     * Trim text to fit within Twitter limit while reserving space for the URL
     */
    private function trimSummaryForTwitter(string $text): string
    {
        $url = " read more on https://corruptionbot.com";
        $maxSummaryLength = 280 - strlen($url);
        
        if (strlen($text) > $maxSummaryLength) {
            return substr($text, 0, $maxSummaryLength - 3) . '...';
        }
        return $text;
    }

    /**
     * Parse AI response as JSON
     * Handles various response formats and extracts structured data
     */
    private function parseAIResponse(string $content): ?array
    {
        // Clean the content first
        $content = trim($content);
        
        // Try to extract JSON from code blocks if present
        if (preg_match('/```json\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        } elseif (preg_match('/```\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }
        
        // Try to parse as JSON
        $data = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
        
        // If JSON parsing fails, try to extract JSON from the text
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $jsonText = $matches[0];
            $data = json_decode($jsonText, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
        
        $this->logger->warning('Failed to parse AI response as JSON: ' . substr($content, 0, 200) . '...');
        return null;
    }
} 