<?php

namespace App\Service;

use App\Entity\News;
use App\Entity\Politician;
use App\Entity\PoliticianScandal;
use App\Repository\NewsRepository;
use App\Repository\PoliticianRepository;
use App\Repository\PoliticianScandalRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;

class ShadyMeterService
{
    public function __construct(
        private readonly NewsRepository $newsRepository,
        private readonly PoliticianRepository $politicianRepository,
        private readonly PoliticianScandalRepository $scandalRepository,
        private readonly string $openaiApiKey,
        private readonly LoggerInterface $logger
    ) {
        // Set PHP execution time limits for long-running API calls
        ini_set('max_execution_time', 120); // 2 minutes
        ini_set('memory_limit', '256M'); // Increase memory limit
        ini_set('default_socket_timeout', 60); // Socket timeout
    }

    public function generateNews(string $country): array
    {
        // Check if we already have news for today
        $existingNews = $this->newsRepository->findByCountryAndToday($country);
        if ($existingNews) {
            return [
                'id' => $existingNews->getId(),
                'news' => $existingNews->getNews(),
                'country' => $country,
                'cached' => true,
                'createdAt' => $existingNews->getCreatedAt()->format('c')
            ];
        }

        // Generate news using OpenAI
        $news = $this->generateNewsWithOpenAI($country);
        
        // Save to database
        $newsEntity = new News();
        $newsEntity->setCountry($country);
        $newsEntity->setNews($news);
        $this->newsRepository->save($newsEntity, true);

        return [
            'id' => $newsEntity->getId(),
            'news' => $news,
            'country' => $country,
            'cached' => false,
            'createdAt' => $newsEntity->getCreatedAt()->format('c')
        ];
    }

    public function getNews(Request $request): array
    {
        $country = $request->query->get('country');
        $todayOnly = $request->query->get('todayOnly', false);

        if ($country) {
            if ($todayOnly) {
                $news = $this->newsRepository->findByCountryAndToday($country);
                return $news ? [$news] : [];
            }
            return $this->newsRepository->findByCountry($country);
        }

        if ($todayOnly) {
            return $this->newsRepository->findTodayOnly();
        }

        // Get all news with limit to reduce data transfer
        return $this->newsRepository->findAll();
    }

    public function generatePoliticians(string $country): array
    {
        // Check if we already have politicians for this country in the last 30 days
        $existingPoliticians = $this->politicianRepository->findByCountryAndLast30Days($country);
        
        if (!empty($existingPoliticians)) {
            // Return existing politicians instead of generating new ones
            $savedPoliticians = [];
            foreach ($existingPoliticians as $politician) {
                $savedPoliticians[] = [
                    'id' => $politician->getId(),
                    'fullName' => $politician->getFullName(),
                    'country' => $politician->getCountry(),
                    'party' => $politician->getParty(),
                    'position' => $politician->getPosition(),
                    'score' => $politician->getScore(),
                    'status' => $politician->getStatus(),
                    'note' => $politician->getNote(),
                    'created' => false,
                    'cached' => true
                ];
            }
            
            return $savedPoliticians;
        }

        // Generate new politicians using AI if none exist in the last 30 days
        $politicians = $this->generatePoliticiansWithOpenAI($country);
        
        $savedPoliticians = [];
        foreach ($politicians as $politicianData) {
            // Check if politician with same full name and country already exists
            $existingPolitician = $this->politicianRepository->findByFullNameAndCountry($politicianData['fullName'], $country);
            
            if ($existingPolitician) {
                // Skip if politician already exists
                $this->logger->info("ShadyMeter: Skipping duplicate politician - {$politicianData['fullName']} from {$country} already exists");
                $savedPoliticians[] = [
                    'id' => $existingPolitician->getId(),
                    'fullName' => $existingPolitician->getFullName(),
                    'country' => $existingPolitician->getCountry(),
                    'party' => $existingPolitician->getParty(),
                    'position' => $existingPolitician->getPosition(),
                    'score' => $existingPolitician->getScore(),
                    'status' => $existingPolitician->getStatus(),
                    'note' => $existingPolitician->getNote(),
                    'created' => false,
                    'cached' => true,
                    'skipped' => true
                ];
                continue;
            }
            
            $politician = new Politician();
            $politician->setFullName($politicianData['fullName']);
            $politician->setCountry($country);
            $politician->setParty($politicianData['party'] ?? null);
            $politician->setPosition($politicianData['position'] ?? null);
            $politician->setScore($politicianData['score']);
            $politician->setStatus($politicianData['status'] ?? 'active');
            $politician->setNote($politicianData['note'] ?? null);
            $politician->setTrending(false);

            $this->politicianRepository->save($politician, true);
            $this->logger->info("ShadyMeter: Created new politician - {$politician->getFullName()} from {$country}");
            
            $savedPoliticians[] = [
                'id' => $politician->getId(),
                'fullName' => $politician->getFullName(),
                'country' => $politician->getCountry(),
                'party' => $politician->getParty(),
                'position' => $politician->getPosition(),
                'score' => $politician->getScore(),
                'status' => $politician->getStatus(),
                'note' => $politician->getNote(),
                'created' => true,
                'cached' => false
            ];
        }

        return $savedPoliticians;
    }

    public function getPoliticians(string $country): array
    {
        return $this->politicianRepository->findByCountry($country);
    }

    /**
     * Search politicians by name containing search string and country
     */
    public function searchPoliticians(string $searchString, string $country, int $limit = 20): array
    {
        return $this->politicianRepository->searchByNameAndCountry($searchString, $country, $limit);
    }

    /**
     * Search for politically affiliated people by name and country using AI web search
     */
    public function searchPoliticallyAffiliatedPeople(string $name, string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $input = "Search the internet for anyone named '{$name}' in {$country} who has appeared in news articles, reports, or public records related to government, politics, political scandals, corruption allegations, public office, government positions, political parties, or political activities. This includes:

- Current or former politicians, government officials, and public servants
- Political party members, leaders, or activists
- Individuals involved in political scandals or corruption cases
- Government contractors or business people with political connections
- Political advisors, lobbyists, or campaign staff
- Anyone mentioned in political news, government reports, or political investigations
- People involved in political protests, movements, or political events

For each person found, return a JSON object with: fullName (their complete name), position (their current or former position, role, or title), and party (their political party, affiliation, or 'Independent'/'Unknown' if not specified). Include the country where they are primarily active.

Return as a JSON array. Only include real, documented individuals with verifiable news coverage. If no relevant people are found with this name, return an empty array.";

        // Debug: Log the API key status (masked for security)
        $maskedKey = substr($this->openaiApiKey, 0, 8) . '...' . substr($this->openaiApiKey, -4);
        $this->logger->info("ShadyMeter: Using OpenAI API key: {$maskedKey}");
        $this->logger->info("ShadyMeter: Input: " . $input);
        
        try {
            // Use /responses endpoint with gpt-4.1 and web search
            $data = [
                'model' => 'gpt-4.1',
                'tools' => [
                    [
                        'type' => 'web_search_preview'
                    ]
                ],
                'input' => $input
            ];

            $this->logger->info("ShadyMeter: Request data: " . json_encode($data));

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/responses',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openaiApiKey
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

            $this->logger->info("ShadyMeter: Raw response received: " . substr($response, 0, 500));

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

            $this->logger->info("ShadyMeter: Extracted content: " . $content);
            
            $result = $this->parseOpenAIResponse($content);
            $this->logger->info("ShadyMeter: Parsed result count: " . count($result));
            
            // Check database for each person found and add politician ID if they exist
            $enhancedResult = [];
            foreach ($result as $person) {
                $fullName = $person['fullName'] ?? '';
                $personCountry = $person['country'] ?? $country;
                
                // Search for exact match in database
                $existingPolitician = $this->politicianRepository->findByFullNameAndCountry($fullName, $personCountry);
                
                if ($existingPolitician) {
                    $person['politician_id'] = $existingPolitician->getId();
                    $person['in_database'] = true;
                } else {
                    $person['politician_id'] = null;
                    $person['in_database'] = false;
                }
                
                $enhancedResult[] = $person;
            }
            
            return $enhancedResult;
        } catch (\Exception $e) {
            $this->logger->error("ShadyMeter: OpenAI API error: " . $e->getMessage());
            throw $e;
        }
    }

    public function generateTrendingPoliticians(string $country): array
    {
        // Check if we already have trending politicians for this country created today
        $existingTrending = $this->politicianRepository->findTrendingByCountryAndToday($country);
        
        if (!empty($existingTrending)) {
            $this->logger->info("ShadyMeter: Found existing trending politicians for {$country}");
            $savedTrending = [];
            foreach ($existingTrending as $politician) {
                $savedTrending[] = [
                    'id' => $politician->getId(),
                    'fullName' => $politician->getFullName(),
                    'country' => $politician->getCountry(),
                    'party' => $politician->getParty(),
                    'position' => $politician->getPosition(),
                    'score' => $politician->getScore(),
                    'status' => $politician->getStatus(),
                    'note' => $politician->getNote(),
                    'trending' => $politician->isTrending(),
                    'created' => false,
                    'cached' => true
                ];
            }
            return $savedTrending;
        }

        $this->logger->info("ShadyMeter: No existing trending politicians found for {$country} today. Deleting all existing trending politicians for this country to make room for new ones.");
        
        // Delete all existing trending politicians for this country to make room for new ones
        $allTrendingForCountry = $this->politicianRepository->findTrendingByCountry($country);
        foreach ($allTrendingForCountry as $politician) {
            $this->politicianRepository->remove($politician, true); // Remove and flush immediately
        }
        
        $this->logger->info("ShadyMeter: Deleted " . count($allTrendingForCountry) . " existing trending politicians for {$country}");

        // Generate new trending politicians using AI if none exist for today
        $politicians = $this->generateTrendingPoliticiansWithOpenAI($country);
        
        $savedTrending = [];
        foreach ($politicians as $politicianData) {
            // Check if politician with same full name and country already exists
            $existingPolitician = $this->politicianRepository->findByFullNameAndCountry($politicianData['fullName'], $country);
            
            if ($existingPolitician) {
                // Skip if politician already exists
                $this->logger->info("ShadyMeter: Skipping duplicate trending politician - {$politicianData['fullName']} from {$country} already exists");
                $savedTrending[] = [
                    'id' => $existingPolitician->getId(),
                    'fullName' => $existingPolitician->getFullName(),
                    'country' => $existingPolitician->getCountry(),
                    'party' => $existingPolitician->getParty(),
                    'position' => $existingPolitician->getPosition(),
                    'score' => $existingPolitician->getScore(),
                    'status' => $existingPolitician->getStatus(),
                    'note' => $existingPolitician->getNote(),
                    'trending' => $existingPolitician->isTrending(),
                    'created' => false,
                    'cached' => true,
                    'skipped' => true
                ];
                continue;
            }
            
            $politician = new Politician();
            $politician->setFullName($politicianData['fullName']);
            $politician->setCountry($country);
            $politician->setParty($politicianData['party'] ?? null);
            $politician->setPosition($politicianData['position'] ?? null);
            $politician->setScore($politicianData['score']);
            $politician->setStatus($politicianData['status'] ?? 'active');
            $politician->setNote($politicianData['note'] ?? null);
            $politician->setTrending(true);

            $this->politicianRepository->save($politician, true);
            $this->logger->info("ShadyMeter: Created new trending politician - {$politician->getFullName()} from {$country}");
            
            $savedTrending[] = [
                'id' => $politician->getId(),
                'fullName' => $politician->getFullName(),
                'country' => $politician->getCountry(),
                'party' => $politician->getParty(),
                'position' => $politician->getPosition(),
                'score' => $politician->getScore(),
                'status' => $politician->getStatus(),
                'note' => $politician->getNote(),
                'trending' => $politician->isTrending(),
                'created' => true,
                'cached' => false
            ];
        }

        return $savedTrending;
    }

    public function getTrendingPoliticians(Request $request): array
    {
        $country = $request->query->get('country');
        $todayOnly = $request->query->get('todayOnly', false);

        if ($country) {
            if ($todayOnly) {
                return $this->politicianRepository->findTrendingByCountryAndToday($country);
            }
            return $this->politicianRepository->findTrendingByCountry($country);
        }

        if ($todayOnly) {
            return $this->politicianRepository->findTrendingTodayOnly();
        }

        return $this->politicianRepository->findTrendingByCountry('');
    }

    public function generatePoliticianScandals(string $politician, string $country): array
    {
        // Check for existing document with same politician and country
        $existingScandal = $this->scandalRepository->findByPoliticianAndCountry($politician, $country);
        
        // Check if existing scandal is older than 30 days (1 month)
        $scandalOlderThanMonth = $this->scandalRepository->findByPoliticianAndCountryOlderThan($politician, $country, 30);
        
        if ($existingScandal && !$scandalOlderThanMonth) {
            // Return existing scandal if it's less than a month old
            return [
                'id' => $existingScandal->getId(),
                'politician' => $existingScandal->getPolitician(),
                'country' => $existingScandal->getCountry(),
                'scandals' => $existingScandal->getScandals(),
                'totalCorruptionScore' => $existingScandal->getTotalCorruptionScore(),
                'cached' => true,
                'createdAt' => $existingScandal->getCreatedAt()->format('c')
            ];
        }
        
        // Generate new scandals using AI (either no existing scandal or existing one is older than a month)
        $scandals = $this->generateScandalsWithOpenAI($politician, $country);
        
        if ($existingScandal) {
            // Update the existing document
            $existingScandal->setScandals($scandals['scandals']);
            $existingScandal->setTotalCorruptionScore($scandals['totalCorruptionScore']);
            $existingScandal->setUpdatedAt(new \DateTime());
            $this->scandalRepository->save($existingScandal, true);
            
            return [
                'id' => $existingScandal->getId(),
                'politician' => $existingScandal->getPolitician(),
                'country' => $existingScandal->getCountry(),
                'scandals' => $existingScandal->getScandals(),
                'totalCorruptionScore' => $existingScandal->getTotalCorruptionScore(),
                'updated' => true,
                'cached' => false
            ];
        } else {
            // Create new document
            $scandalEntity = new PoliticianScandal();
            $scandalEntity->setPolitician($politician);
            $scandalEntity->setCountry($country);
            $scandalEntity->setScandals($scandals['scandals']);
            $scandalEntity->setTotalCorruptionScore($scandals['totalCorruptionScore']);
            
            $this->scandalRepository->save($scandalEntity, true);

            return [
                'id' => $scandalEntity->getId(),
                'politician' => $scandalEntity->getPolitician(),
                'country' => $scandalEntity->getCountry(),
                'scandals' => $scandalEntity->getScandals(),
                'totalCorruptionScore' => $scandalEntity->getTotalCorruptionScore(),
                'created' => true,
                'cached' => false
            ];
        }
    }

    public function getPoliticianScandals(string $politician): array
    {
        return $this->scandalRepository->findByPolitician($politician);
    }

    public function generateScandalArticle(string $politician, string $country, array $scandal): array
    {
        // Validate required fields
        if (empty($scandal['title']) || empty($scandal['year'])) {
            throw new \Exception('Missing required fields: title and year are required');
        }

        // Find the scandal document first
        $scandalEntity = $this->scandalRepository->findByPoliticianAndCountry($politician, $country);
        if (!$scandalEntity) {
            throw new \Exception('No scandal document found for this politician');
        }

        // Get the scandals array and find the specific scandal
        $scandals = $scandalEntity->getScandals();
        $scandalFound = false;
        $existingArticle = null;
        
        foreach ($scandals as $s) {
            if ($s['title'] === $scandal['title'] && (string)$s['year'] === (string)$scandal['year']) {
                $scandalFound = true;
                // Check if article already exists
                if (isset($s['article']) && !empty($s['article'])) {
                    $existingArticle = [
                        'article' => $s['article'],
                        'timeline' => $s['timeline'] ?? [],
                        'involved_persons' => $s['involved_persons'] ?? []
                    ];
                }
                break;
            }
        }
        
        if (!$scandalFound) {
            throw new \Exception('Scandal not found in document');
        }

        // If article already exists, return it without calling AI
        if ($existingArticle) {
            return $existingArticle;
        }

        // Generate the article using OpenAI only if it doesn't exist
        $articleData = $this->generateArticleWithOpenAI($politician, $country, $scandal);
        
        // Update the scandal with the new article data
        foreach ($scandals as &$s) {
            if ($s['title'] === $scandal['title'] && (string)$s['year'] === (string)$scandal['year']) {
                $s['article'] = $articleData['article'];
                $s['timeline'] = $articleData['timeline'];
                $s['involved_persons'] = $articleData['involved_persons'];
                break;
            }
        }

        // Update the entity and save to database
        $scandalEntity->setScandals($scandals);
        $scandalEntity->setUpdatedAt(new \DateTime());
        $this->scandalRepository->save($scandalEntity, true);
        
        return $articleData;
    }

    public function getCountryStatistics(Request $request): array
    {
        $country = $request->query->get('country');
        
        if (!$country || empty($country)) {
            return [
                'error' => 'Missing or invalid country',
                'details' => 'Country query parameter is required'
            ];
        }

        // Get politician counts for the country
        $politicians = $this->politicianRepository->findByCountry($country);
        $trendingPoliticians = $this->politicianRepository->findTrendingByCountry($country);
        
        // Get news count for the country
        $news = $this->newsRepository->findByCountry($country);
        
        // Get scandal count for the country
        $scandals = $this->scandalRepository->findByCountry($country);

        return [
            'country' => $country,
            'statistics' => [
                'totalPoliticians' => count($politicians),
                'trendingPoliticians' => count($trendingPoliticians),
                'totalNews' => count($news),
                'totalScandals' => count($scandals)
            ],
            'politicians' => $politicians,
            'trendingPoliticians' => $trendingPoliticians,
            'news' => $news,
            'scandals' => $scandals
        ];
    }

    public function generateGlobalTrendingNews(): array
    {
        // Check if we already have global trending news created within the last 24 hours
        $existingNews = $this->newsRepository->findByCountryAndLast24Hours('global');
        if ($existingNews) {
            return [
                'id' => $existingNews->getId(),
                'news' => $existingNews->getNews(),
                'country' => 'global',
                'cached' => true,
                'createdAt' => $existingNews->getCreatedAt()->format('c')
            ];
        }

        // Generate global trending news using OpenAI
        $news = $this->generateGlobalTrendingNewsWithOpenAI();
        
        // Save to database using existing news table
        $newsEntity = new News();
        $newsEntity->setCountry('global');
        $newsEntity->setNews($news);
        $this->newsRepository->save($newsEntity, true);

        return [
            'id' => $newsEntity->getId(),
            'news' => $news,
            'country' => 'global',
            'cached' => false,
            'createdAt' => $newsEntity->getCreatedAt()->format('c')
        ];
    }

    public function getGlobalTrendingNews(Request $request): array
    {
        $todayOnly = $request->query->get('todayOnly', false);
        $last24Hours = $request->query->get('last24Hours', false);

        if ($last24Hours) {
            $news = $this->newsRepository->findByCountryAndLast24Hours('global');
            return $news ? [$news] : [];
        }

        if ($todayOnly) {
            $news = $this->newsRepository->findByCountryAndToday('global');
            return $news ? [$news] : [];
        }

        // Get all global news
        return $this->newsRepository->findByCountry('global');
    }

    public function generateFullStory(int $newsId, string $storyKey): array
    {
        // Find the news by ID
        $news = $this->newsRepository->find($newsId);
        if (!$news) {
            throw new \Exception('News not found');
        }

        // Get the news array and find the specific story
        $newsArray = $news->getNews();
        $storyFound = false;
        $targetStory = null;
        
        foreach ($newsArray as &$story) {
            if (isset($story['key']) && $story['key'] === $storyKey) {
                // Check if full story already exists
                if (isset($story['fullStory'])) {
                    return [
                        'newsId' => $newsId,
                        'storyKey' => $storyKey,
                        'fullStory' => $story['fullStory'],
                        'cached' => true
                    ];
                }
                
                $targetStory = &$story;
                $storyFound = true;
                break;
            }
        }
        
        if (!$storyFound) {
            throw new \Exception('Story not found in news');
        }

        // Generate the full story using OpenAI
        $fullStory = $this->generateFullStoryWithOpenAI($targetStory);
        
        // Add the full story to the target story
        $targetStory['fullStory'] = $fullStory;
        
        // Update the news entity and save to database
        $news->setNews($newsArray);
        $news->setUpdatedAt(new \DateTime());
        $this->newsRepository->save($news, true);
        
        return [
            'newsId' => $newsId,
            'storyKey' => $storyKey,
            'fullStory' => $fullStory,
            'cached' => false
        ];
    }

    public function generateFullStoryForCountry(int $newsId, string $storyKey): array
    {
        // Find the news by ID
        $news = $this->newsRepository->find($newsId);
        if (!$news) {
            throw new \Exception('News not found');
        }

        $country = $news->getCountry();

        // Get the news array and find the specific story
        $newsArray = $news->getNews();
        $storyFound = false;
        $targetStory = null;
        
        foreach ($newsArray as &$story) {
            if (isset($story['key']) && $story['key'] === $storyKey) {
                // Check if full story already exists
                if (isset($story['fullStory'])) {
                    return [
                        'newsId' => $newsId,
                        'storyKey' => $storyKey,
                        'fullStory' => $story['fullStory'],
                        'cached' => true
                    ];
                }
                
                $targetStory = &$story;
                $storyFound = true;
                break;
            }
        }
        
        if (!$storyFound) {
            throw new \Exception('Story not found in news');
        }

        // Generate the full story using OpenAI
        $fullStory = $this->generateFullStoryWithOpenAI($targetStory, $country);
        
        // Add the full story to the target story
        $targetStory['fullStory'] = $fullStory;
        
        // Update the news entity and save to database
        $news->setNews($newsArray);
        $news->setUpdatedAt(new \DateTime());
        $this->newsRepository->save($news, true);
        
        return [
            'newsId' => $newsId,
            'storyKey' => $storyKey,
            'fullStory' => $fullStory,
            'cached' => false
        ];
    }

    /**
     * Get a politician by ID
     */
    public function getPoliticianById(int $id): ?array
    {
        $politician = $this->politicianRepository->find($id);
        
        if (!$politician) {
            return null;
        }

        // Convert entity to array for consistent response format
        return [
            'id' => $politician->getId(),
            'fullName' => $politician->getFullName(),
            'country' => $politician->getCountry(),
            'party' => $politician->getParty(),
            'position' => $politician->getPosition(),
            'score' => $politician->getScore(),
            'status' => $politician->getStatus(),
            'note' => $politician->getNote(),
            'trending' => $politician->isTrending(),
            'careerTimeline' => $politician->getCareerTimeline(),
            'careerTimelineUpdatedAt' => $politician->getCareerTimelineUpdatedAt(),
            'createdAt' => $politician->getCreatedAt(),
            'updatedAt' => $politician->getUpdatedAt()
        ];
    }

    /**
     * Generate career timeline for a politician using AI
     */
    public function generateCareerTimeline(int $politicianId): array
    {
        $politician = $this->politicianRepository->find($politicianId);
        
        if (!$politician) {
            throw new \Exception('Politician not found with ID: ' . $politicianId);
        }

        // Check if career timeline exists and was updated within the last month
        if ($politician->getCareerTimeline() !== null && $politician->getCareerTimelineUpdatedAt() !== null) {
            $oneMonthAgo = new \DateTime();
            $oneMonthAgo->modify('-1 month');
            
            if ($politician->getCareerTimelineUpdatedAt() > $oneMonthAgo) {
                return [
                    'id' => $politician->getId(),
                    'fullName' => $politician->getFullName(),
                    'careerTimeline' => $politician->getCareerTimeline(),
                    'careerTimelineUpdatedAt' => $politician->getCareerTimelineUpdatedAt(),
                    'cached' => true,
                    'message' => 'Career timeline is up to date (updated within last month)'
                ];
            }
        }

        try {
            // Generate career timeline using AI
            $careerTimeline = $this->generateCareerTimelineWithOpenAI($politician->getFullName(), $politician->getCountry());
            
            // Save to database
            $politician->setCareerTimeline($careerTimeline);
            $politician->setCareerTimelineUpdatedAt(new \DateTime());
            $politician->setUpdatedAt(new \DateTime());
            $this->politicianRepository->save($politician, true);

            $this->logger->info("ShadyMeter: Generated career timeline for politician - {$politician->getFullName()}");

            return [
                'id' => $politician->getId(),
                'fullName' => $politician->getFullName(),
                'careerTimeline' => $careerTimeline,
                'careerTimelineUpdatedAt' => $politician->getCareerTimelineUpdatedAt(),
                'cached' => false,
                'message' => 'Career timeline generated successfully'
            ];
        } catch (\Exception $e) {
            $this->logger->error("ShadyMeter: Error generating career timeline for politician {$politician->getFullName()}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get career timeline for a politician
     */
    public function getCareerTimeline(int $politicianId): ?array
    {
        $politician = $this->politicianRepository->find($politicianId);
        
        if (!$politician) {
            return null;
        }

        return [
            'id' => $politician->getId(),
            'fullName' => $politician->getFullName(),
            'careerTimeline' => $politician->getCareerTimeline(),
            'careerTimelineUpdatedAt' => $politician->getCareerTimelineUpdatedAt(),
            'hasTimeline' => $politician->getCareerTimeline() !== null,
            'isUpToDate' => $this->isCareerTimelineUpToDate($politician)
        ];
    }

    /**
     * Check if career timeline is up to date (updated within last month)
     */
    private function isCareerTimelineUpToDate(Politician $politician): bool
    {
        if ($politician->getCareerTimeline() === null || $politician->getCareerTimelineUpdatedAt() === null) {
            return false;
        }

        $oneMonthAgo = new \DateTime();
        $oneMonthAgo->modify('-1 month');
        
        return $politician->getCareerTimelineUpdatedAt() > $oneMonthAgo;
    }

    private function generateNewsWithOpenAI(string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        // Get current date range for the prompt (last 7 days)
        $currentDate = new \DateTime();
        $startDate = clone $currentDate;
        $startDate->modify('-7 days');
        
        $startDateStr = $startDate->format('F j, Y');
        $endDateStr = $currentDate->format('F j, Y');

        $input = "Hey there, comedy detective! 🕵️‍♂️ Let's find the top 3 most hilariously shady corruption stories from {$country} between {$startDateStr} and {$endDateStr}. We're talking about politicians who think 'creative accounting' means 'creative ways to hide money'! 😂 Focus on political corruption, embezzlement, bribery, and accountability issues - but make it entertaining! For each story, return a JSON object with: key (unique identifier like 'story_1', 'story_2', 'story_3'), title (headline - make it catchy and slightly sarcastic), summary (2-3 sentences with a dash of wit), impact (high/medium/low), and source (if known). Return as a JSON array. Only include real, documented events - but feel free to add some comedic flair to the descriptions!";

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
            CURLOPT_URL => 'https://api.openai.com/v1/responses',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
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
        
        // Clean the response - extract JSON from code blocks if present
        $cleaned = $this->cleanOpenAIResponse($content);
        
        try {
            $news = json_decode($cleaned, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
            }
            
            if (!is_array($news)) {
                throw new \Exception('OpenAI response is not an array');
            }
            
            return $news;
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse OpenAI response as JSON: ' . $e->getMessage() . ' Raw: ' . $content);
        }
    }

    private function generateFullStoryWithOpenAI(array $story, ?string $country = null): string
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $title = $story['title'] ?? 'Unknown Story';
        $summary = $story['summary'] ?? '';
        $impact = $story['impact'] ?? 'medium';
        $source = $story['source'] ?? '';

        // Generate full story prompt
        $countryContext = $country ? "Country: {$country}\n" : '';
        $input = "Alright, comedy journalist extraordinaire! 🎭 Time to write a hilariously detailed news article (500 words) about this corruption story that's so shady it needs sunglasses! 😎\n\n{$countryContext}Title: {$title}\nSummary: {$summary}\nImpact: {$impact}\nSource: {$source}\n\nInclude background context, timeline of events, key players involved (the usual suspects! 🕵️‍♀️), international implications, and potential consequences. Use a witty, entertaining tone that makes readers chuckle while learning about serious issues. Think 'The Daily Show' meets investigative journalism! Return only the article text.";

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
            CURLOPT_URL => 'https://api.openai.com/v1/responses',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
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

        $fullStory = trim($content);
        if (empty($fullStory)) {
            throw new \Exception('No full story generated');
        }

        return $fullStory;
    }

    private function generatePoliticiansWithOpenAI(string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $input = "Hey there, political comedy scout! 🎪 Let's find the 20 most 'colorful' politicians in {$country} who've been caught with their hands in the cookie jar (or should we say, the taxpayer's wallet? 😂). These are the folks who think 'transparency' means 'transparently hiding money'! For each, return a JSON object with these keys: fullName, country, party, position, score (1-100, higher means more allegations - think of it as their 'shadiness rating'! 🌚), status (active, retired, or deceased), and note (a witty one-sentence reason that'll make readers giggle). Return as a JSON array.";

        // Use /responses endpoint with gpt-4.1 and web search
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
            CURLOPT_URL => 'https://api.openai.com/v1/responses',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
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
        
        return $this->parseOpenAIResponse($content);
    }

    private function generateTrendingPoliticiansWithOpenAI(string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $currentYear = date('Y');
        $input = "🎭 Comedy Central presents: The 10 Most Trending Politicians in {$country} for corruption scandals in {$currentYear}! These are the political rock stars who've mastered the art of 'creative bookkeeping' and 'alternative facts'! 😂 For each, return a JSON object with: fullName, country, party, position, score (1-100, higher means more trending - their 'viral shadiness' rating! 🌟), status (active, retired, or deceased), note (one witty sentence that captures their 'unique talents'), and a 'trending' field set to true. Return as a JSON array. Let's make this the most entertaining political roast of the year! 🎪";

        // Debug: Log the API key status (masked for security)
        $maskedKey = substr($this->openaiApiKey, 0, 8) . '...' . substr($this->openaiApiKey, -4);
        $this->logger->info("ShadyMeter: Using OpenAI API key: {$maskedKey}");
        $this->logger->info("ShadyMeter: Input: " . $input);
        
        try {
            // Use /responses endpoint with gpt-4.1 and web search
            $data = [
                'model' => 'gpt-4.1',
                'tools' => [
                    [
                        'type' => 'web_search_preview'
                    ]
                ],
                'input' => $input
            ];

            $this->logger->info("ShadyMeter: Request data: " . json_encode($data));

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/responses',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openaiApiKey
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

            $this->logger->info("ShadyMeter: Raw response received: " . substr($response, 0, 500));

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

            $this->logger->info("ShadyMeter: Extracted content: " . $content);
            
            $result = $this->parseOpenAIResponse($content);
            $this->logger->info("ShadyMeter: Parsed result count: " . count($result));
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("ShadyMeter: OpenAI API error: " . $e->getMessage());
            throw $e;
        }
    }

    private function generateScandalsWithOpenAI(string $politician, string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $input = "🎪 Welcome to the 'Greatest Hits' collection of {$politician} from {$country}! Let's dig up all the juicy corruption-related scandals that made this political superstar famous (or should we say infamous? 😂). For each scandal, include: a specific, real-world title (make it catchy and slightly sarcastic - no boring 'Scandal 1' titles!), the year, a 1-sentence description with concrete details and a dash of wit (no generic descriptions - we want the tea! ☕), status (proven, under_investigation, cleared, or unresolved), an impact score from 1–10 (their 'shadiness level'! 🌚), a province/region field, and a sector field. For the province/region: if the scandal is specific to a particular province/state/region within the country, include that specific location. If the scandal is national-level or affects the entire country, use 'National'. For the sector: categorize the scandal by the sector it occurred in (e.g., energy, education, health, defense, infrastructure, agriculture, finance, transportation, telecommunications, etc.). If the scandal spans multiple sectors, choose the primary one. Do not invent scandals; only use real, documented events. If no scandals are found, return an empty array for scandals and a totalCorruptionScore of 0 (maybe they're just really good at hiding things! 🤷‍♂️). Return the result as a JSON object with keys: politician, country, scandals (array), and totalCorruptionScore.";

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
            CURLOPT_URL => 'https://api.openai.com/v1/responses',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
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
        
        // Clean the response - extract JSON from code blocks if present
        $cleaned = $this->cleanOpenAIResponse($content);
        
        try {
            $result = json_decode($cleaned, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse OpenAI response as JSON: ' . $e->getMessage() . ' Raw: ' . $content);
        }

        // Validate structure
        if (!isset($result['politician']) || !isset($result['country']) || !is_array($result['scandals']) || !isset($result['totalCorruptionScore'])) {
            throw new \Exception('OpenAI response missing required fields');
        }

        // Convert snake_case to camelCase for frontend compatibility
        $processedResult = [
            'politician' => $result['politician'],
            'country' => $result['country'],
            'scandals' => array_map(function($scandal) {
                // Handle both impact_score and impactScore, prioritize impact_score if both exist
                $impactScore = isset($scandal['impact_score']) ? $scandal['impact_score'] : ($scandal['impactScore'] ?? 0);
                
                return [
                    'title' => $scandal['title'] ?? '',
                    'year' => $scandal['year'] ?? 0,
                    'description' => $scandal['description'] ?? '',
                    'status' => $scandal['status'] ?? 'unresolved',
                    'impactScore' => $impactScore,
                    'province' => $scandal['province'] ?? $scandal['region'] ?? 'National',
                    'sector' => $scandal['sector'] ?? 'general'
                ];
            }, $result['scandals']),
            'totalCorruptionScore' => $result['totalCorruptionScore']
        ];

        return $processedResult;
    }

    protected function generateArticleWithOpenAI(string $politician, string $country, array $scandal): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $scandalTitle = $scandal['title'] ?? 'Unknown Scandal';
        $scandalYear = $scandal['year'] ?? 'Unknown Year';
        $scandalDescription = $scandal['description'] ?? '';
        $scandalProvince = $scandal['province'] ?? 'National';
        $scandalSector = $scandal['sector'] ?? 'general';

        // Generate article prompt with timeline and full names requirements
        $input = "🎭 Comedy journalist extraordinaire, it's time to write a hilariously detailed article (300-500 words) about this political scandal involving {$politician} from {$country}! This is the kind of story that writes itself - and boy, does it have plot twists! 😂\n\nTitle: {$scandalTitle}\nYear: {$scandalYear}\nLocation: {$scandalProvince}\nSector: {$scandalSector}\nDescription: {$scandalDescription}\n\nIMPORTANT REQUIREMENTS:\n1. Write a detailed journalistic article (200-250 words) with a witty, satirical tone\n2. Include a chronological timeline of key events in this scandal\n3. Always use FULL NAMES (first and last names) for ALL politicians and persons involved\n4. Include background, impact, and aftermath\n5. Make it entertaining while being informative\n\nReturn your response in this exact JSON format:\n{\n  \"article\": \"[Your detailed article text here]\",\n  \"timeline\": [\n    {\n      \"date\": \"[Date in YYYY-MM-DD format or year if specific date unknown]\",\n      \"event\": \"[Description of what happened on this date]\"\n    }\n  ],\n  \"involved_persons\": [\n    {\n      \"full_name\": \"[First and Last Name]\",\n      \"role\": \"[Their role in the scandal]\",\n      \"position\": \"[Their political position or job title at the time]\"\n    }\n  ]\n}";

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
            CURLOPT_URL => 'https://api.openai.com/v1/responses',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
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

        $content = trim($content);
        if (empty($content)) {
            throw new \Exception('No article generated');
        }

        // Clean the response to extract JSON from markdown code blocks if present
        $cleanedContent = $this->cleanOpenAIResponse($content);
        
        // Try to parse the response as JSON
        $parsedResponse = json_decode($cleanedContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON parsing fails, treat it as plain text and create a basic structure
            $this->logger->warning('Failed to parse OpenAI response as JSON, treating as plain text: ' . json_last_error_msg());
            return [
                'article' => $content,
                'timeline' => [],
                'involved_persons' => []
            ];
        }

        // Validate the required fields
        if (!isset($parsedResponse['article']) || empty($parsedResponse['article'])) {
            throw new \Exception('Generated response missing required article field');
        }

        // Ensure timeline and involved_persons are arrays
        $timeline = isset($parsedResponse['timeline']) && is_array($parsedResponse['timeline']) ? $parsedResponse['timeline'] : [];
        $involvedPersons = isset($parsedResponse['involved_persons']) && is_array($parsedResponse['involved_persons']) ? $parsedResponse['involved_persons'] : [];

        return [
            'article' => $parsedResponse['article'],
            'timeline' => $timeline,
            'involved_persons' => $involvedPersons
        ];
    }

    private function generateGlobalTrendingNewsWithOpenAI(): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        // Get current date range for the prompt (last 7 days)
        $currentDate = new \DateTime();
        $startDate = clone $currentDate;
        $startDate->modify('-7 days');
        
        $startDateStr = $startDate->format('F j, Y');
        $endDateStr = $currentDate->format('F j, Y');

        $input = "🌍 Welcome to the Global Comedy of Errors! Let's find the top 3 most hilariously shady corruption stories from around the world between {$startDateStr} and {$endDateStr}. We're talking about international political shenanigans that are so absurd they could be comedy sketches! 😂 Focus on political corruption, embezzlement, bribery, and accountability issues that have international impact or involve multiple countries - the kind of stories that make you think 'you can't make this stuff up!' For each story, return a JSON object with: key (unique identifier like 'story_1', 'story_2', 'story_3'), title (headline - make it catchy and slightly sarcastic), summary (2-3 sentences with international flair and wit), impact (high/medium/low), and source (if known). Return as a JSON array. Only include real, documented events - but feel free to add some global comedic flair! 🌟";

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
            CURLOPT_URL => 'https://api.openai.com/v1/responses',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
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
        
        // Clean the response - extract JSON from code blocks if present
        $cleaned = $this->cleanOpenAIResponse($content);
        
        try {
            $news = json_decode($cleaned, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
            }
            
            if (!is_array($news)) {
                throw new \Exception('OpenAI response is not an array');
            }
            
            return $news;
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse OpenAI response as JSON: ' . $e->getMessage() . ' Raw: ' . $content);
        }
    }

    /**
     * Make a request to OpenAI API with retry mechanism
     */
    private function makeOpenAIRequest(string $prompt, int $maxRetries = 2): string
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts <= $maxRetries) {
            try {
                return $this->executeOpenAIRequest($prompt);
            } catch (\Exception $e) {
                $lastError = $e;
                $attempts++;
                
                // If it's a timeout error and we have retries left, wait and retry
                if ($attempts <= $maxRetries && (
                    strpos($e->getMessage(), 'timeout') !== false ||
                    strpos($e->getMessage(), 'cURL error') !== false
                )) {
                    // Wait before retrying (exponential backoff)
                    sleep($attempts * 2);
                    continue;
                }
                
                // If it's not a retryable error or we're out of retries, throw the error
                throw $e;
            }
        }

        throw $lastError;
    }

    /**
     * Execute a single OpenAI API request
     */
    private function executeOpenAIRequest(string $prompt): string
    {
        $data = [
            'model' => 'gpt-4-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that returns only valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60, // Increased timeout
            CURLOPT_CONNECTTIMEOUT => 10, // Connection timeout
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
        if (!$responseData || !isset($responseData['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response from OpenAI API');
        }

        return $responseData['choices'][0]['message']['content'];
    }

    /**
     * Parse OpenAI response and extract JSON
     */
    private function parseOpenAIResponse(string $rawResponse): array
    {
        // Clean the response - extract JSON from code blocks if present
        $cleaned = $this->cleanOpenAIResponse($rawResponse);
        
        try {
            $parsed = json_decode($cleaned, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse JSON: ' . json_last_error_msg());
            }
            
            if (!is_array($parsed)) {
                throw new \Exception('OpenAI response is not an array');
            }
            
            return $parsed;
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse OpenAI response as JSON: ' . $e->getMessage() . ' Raw: ' . $rawResponse);
        }
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
     * Generate career timeline using OpenAI
     */
    private function generateCareerTimelineWithOpenAI(string $politicianName, string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $prompt = "Create a comprehensive career timeline for {$politicianName}, a politician from {$country}. 

        Research and provide a detailed chronological timeline of their political career, including:
        - Political positions held (with dates)
        - Key achievements and milestones
        - Party affiliations and changes
        - Elections won or lost
        - Major policy initiatives
        - Controversies or scandals (if any)
        - Educational background and early career
        - Current status

        Return the data as a JSON array with the following structure:
        [
            {
                \"year\": \"2023\",
                \"title\": \"Position or Event Title\",
                \"description\": \"Detailed description of what happened\"
            }
        ]

        Focus on factual, verifiable information. Include at least 10-15 significant events spanning their entire career. Order chronologically from earliest to latest. If exact dates are not known, use approximate years.";

        $rawResponse = $this->makeOpenAIRequest($prompt);
        return $this->parseOpenAIResponse($rawResponse);
    }

    /**
     * Get politician connections based on scandal involvement
     * 
     * Analyzes all scandals and finds connections between politicians
     * who were involved in the same scandals.
     * 
     * @param int|null $politicianId Optional politician ID to filter connections
     */
    public function getPoliticianConnections(?int $politicianId = null): array
    {
        // Get all scandals (not filtered by country)
        $scandals = $this->scandalRepository->findAll();
        
        if (empty($scandals)) {
            return [
                'politician_id_filter' => $politicianId,
                'politician_name_filter' => null,
                'connections' => [],
                'total_connections' => 0,
                'message' => 'No scandals found in the database'
            ];
        }

        // If politician ID is provided, find the politician to get their name
        $politicianName = null;
        if ($politicianId) {
            $politician = $this->politicianRepository->find($politicianId);
            if (!$politician) {
                return [
                    'politician_id_filter' => $politicianId,
                    'politician_name_filter' => null,
                    'connections' => [],
                    'total_connections' => 0,
                    'message' => 'Politician not found with ID: ' . $politicianId
                ];
            }
            $politicianName = $politician->getFullName();
        }

        $connections = [];
        $connectionMap = [];

        // Process each scandal to extract connections
        foreach ($scandals as $scandalEntity) {
            $scandalsArray = $scandalEntity->getScandals();
            $mainPolitician = $scandalEntity->getPolitician();
            
            // If politician filter is provided, skip scandals not involving this politician
            if ($politicianName && $mainPolitician !== $politicianName) {
                // Check if the filtered politician is involved in this scandal
                $politicianInvolved = false;
                foreach ($scandalsArray as $scandal) {
                    if (isset($scandal['involved_persons']) && is_array($scandal['involved_persons'])) {
                        foreach ($scandal['involved_persons'] as $involvedPerson) {
                            if (($involvedPerson['full_name'] ?? '') === $politicianName) {
                                $politicianInvolved = true;
                                break 2;
                            }
                        }
                    }
                }
                if (!$politicianInvolved) {
                    continue;
                }
            }
            
            foreach ($scandalsArray as $scandal) {
                // Check if this scandal has involved persons
                if (isset($scandal['involved_persons']) && is_array($scandal['involved_persons'])) {
                    $involvedPersons = $scandal['involved_persons'];
                    
                    // Create connections between the main politician and all involved persons
                    foreach ($involvedPersons as $involvedPerson) {
                        $involvedName = $involvedPerson['full_name'] ?? '';
                        
                        if (!empty($involvedName) && $involvedName !== $mainPolitician) {
                            // Create a unique connection key (alphabetically sorted to avoid duplicates)
                            $names = [$mainPolitician, $involvedName];
                            sort($names);
                            $connectionKey = $names[0] . '|' . $names[1];
                            
                            if (!isset($connectionMap[$connectionKey])) {
                                $connectionMap[$connectionKey] = [
                                    'politician1' => $names[0],
                                    'politician2' => $names[1],
                                    'scandals' => [],
                                    'connection_strength' => 0
                                ];
                            }
                            
                            // Add this scandal to the connection
                            $scandalInfo = [
                                'title' => $scandal['title'] ?? 'Unknown Scandal',
                                'year' => $scandal['year'] ?? 'Unknown Year',
                                'description' => $scandal['description'] ?? '',
                                'main_politician' => $mainPolitician,
                                'involved_person' => $involvedName,
                                'role' => $involvedPerson['role'] ?? 'Unknown Role',
                                'position' => $involvedPerson['position'] ?? 'Unknown Position',
                                'scandal_id' => $scandalEntity->getId(),
                                'country' => $scandalEntity->getCountry()
                            ];
                            
                            $connectionMap[$connectionKey]['scandals'][] = $scandalInfo;
                            $connectionMap[$connectionKey]['connection_strength']++;
                        }
                    }
                }
            }
        }

        // Convert connection map to array and sort by connection strength
        foreach ($connectionMap as $connection) {
            $connections[] = $connection;
        }
        
        // Sort by connection strength (number of shared scandals) in descending order
        usort($connections, function($a, $b) {
            return $b['connection_strength'] - $a['connection_strength'];
        });

        return [
            'politician_id_filter' => $politicianId,
            'politician_name_filter' => $politicianName,
            'connections' => $connections,
            'total_connections' => count($connections),
            'total_scandals_analyzed' => count($scandals),
            'message' => count($connections) > 0 
                ? ($politicianName ? "Connections found involving {$politicianName}" : 'Connections found based on scandal involvement')
                : 'No connections found'
        ];
    }

    public function getCountryPoliticianConnections(string $country): array
    {
        // Get all scandals for the specific country
        $scandals = $this->scandalRepository->findByCountry($country);
        
        if (empty($scandals)) {
            return [
                'country' => $country,
                'connections' => [],
                'total_connections' => 0,
                'message' => "No scandals found for country: {$country}"
            ];
        }

        $connections = [];
        $connectionMap = [];

        // Process each scandal to extract connections
        foreach ($scandals as $scandalEntity) {
            $scandalsArray = $scandalEntity->getScandals();
            $mainPolitician = $scandalEntity->getPolitician();
            
            foreach ($scandalsArray as $scandal) {
                // Check if this scandal has involved persons
                if (isset($scandal['involved_persons']) && is_array($scandal['involved_persons'])) {
                    $involvedPersons = $scandal['involved_persons'];
                    
                    // Create connections between the main politician and all involved persons
                    foreach ($involvedPersons as $involvedPerson) {
                        $involvedName = $involvedPerson['full_name'] ?? '';
                        
                        if (!empty($involvedName) && $involvedName !== $mainPolitician) {
                            // Create a unique connection key (alphabetically sorted to avoid duplicates)
                            $names = [$mainPolitician, $involvedName];
                            sort($names);
                            $connectionKey = $names[0] . '|' . $names[1];
                            
                            if (!isset($connectionMap[$connectionKey])) {
                                $connectionMap[$connectionKey] = [
                                    'politician1' => $names[0],
                                    'politician2' => $names[1],
                                    'scandals' => [],
                                    'connection_strength' => 0
                                ];
                            }
                            
                            // Add this scandal to the connection
                            $scandalInfo = [
                                'title' => $scandal['title'] ?? 'Unknown Scandal',
                                'year' => $scandal['year'] ?? 'Unknown Year',
                                'description' => $scandal['description'] ?? '',
                                'main_politician' => $mainPolitician,
                                'involved_person' => $involvedName,
                                'role' => $involvedPerson['role'] ?? 'Unknown Role',
                                'position' => $involvedPerson['position'] ?? 'Unknown Position',
                                'scandal_id' => $scandalEntity->getId(),
                                'country' => $scandalEntity->getCountry()
                            ];
                            
                            $connectionMap[$connectionKey]['scandals'][] = $scandalInfo;
                            $connectionMap[$connectionKey]['connection_strength']++;
                        }
                    }
                }
            }
        }

        // Convert connection map to array and sort by connection strength
        foreach ($connectionMap as $connection) {
            $connections[] = $connection;
        }
        
        // Sort by connection strength (number of shared scandals) in descending order
        usort($connections, function($a, $b) {
            return $b['connection_strength'] - $a['connection_strength'];
        });

        return [
            'country' => $country,
            'connections' => $connections,
            'total_connections' => count($connections),
            'total_scandals_analyzed' => count($scandals),
            'message' => count($connections) > 0 
                ? "Connections found for politicians in {$country}"
                : "No connections found for politicians in {$country}"
        ];
    }

    /**
     * Generate politician details using AI based on provided information
     */
    public function generatePoliticianDetails(array $politicianData): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $fullName = $politicianData['fullName'] ?? '';
        $position = $politicianData['position'] ?? '';
        $party = $politicianData['party'] ?? '';

        $input = "Based on the following information about a politician, provide additional details for a corruption tracking database. 

Politician Information:
- Full Name: {$fullName}
- Position: {$position}
- Party: {$party}

Please provide the following details in JSON format:
1. country (the country where this politician is primarily active)
2. score (a corruption risk score from 1-100, where higher means more allegations or suspicious activities - be objective and fair)
3. status (active, retired, deceased, or suspended)
4. note (a brief, factual note about any corruption allegations, scandals, or suspicious activities - keep it professional and factual)

Return ONLY a valid JSON object with these fields: country, score, status, note. If you cannot determine any field, use 'Unknown' for country, 0 for score, 'active' for status, and null for note.";

        // Debug: Log the API key status (masked for security)
        $maskedKey = substr($this->openaiApiKey, 0, 8) . '...' . substr($this->openaiApiKey, -4);
        $this->logger->info("ShadyMeter: Using OpenAI API key: {$maskedKey}");
        $this->logger->info("ShadyMeter: Input: " . $input);
        
        try {
            // Use /responses endpoint with gpt-4.1 and web search
            $data = [
                'model' => 'gpt-4.1',
                'tools' => [
                    [
                        'type' => 'web_search_preview'
                    ]
                ],
                'input' => $input
            ];

            $this->logger->info("ShadyMeter: Request data: " . json_encode($data));

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/responses',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openaiApiKey
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

            $this->logger->info("ShadyMeter: Raw response received: " . substr($response, 0, 500));

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

            $this->logger->info("ShadyMeter: Extracted content: " . $content);
            
            $result = $this->parseOpenAIResponse($content);
            $this->logger->info("ShadyMeter: Parsed result: " . json_encode($result));
            
            // Merge the AI-generated details with the provided data
            $completeData = array_merge($politicianData, $result);
            
            return $completeData;
        } catch (\Exception $e) {
            $this->logger->error("ShadyMeter: OpenAI API error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add a new politician to the database
     */
    public function addPolitician(array $politicianData): array
    {
        try {
            // Check if politician already exists by full name and country
            $fullName = $politicianData['fullName'] ?? '';
            $country = $politicianData['country'] ?? '';
            
            if (empty($fullName) || empty($country)) {
                throw new \Exception('Full name and country are required');
            }
            
            $existingPolitician = $this->politicianRepository->findByFullNameAndCountry($fullName, $country);
            
            if ($existingPolitician) {
                $this->logger->info("ShadyMeter: Politician already exists - {$fullName} from {$country}");
                return [
                    'success' => false,
                    'message' => 'Politician with this name and country already exists',
                    'politician_id' => $existingPolitician->getId(),
                    'politician' => [
                        'id' => $existingPolitician->getId(),
                        'fullName' => $existingPolitician->getFullName(),
                        'country' => $existingPolitician->getCountry(),
                        'party' => $existingPolitician->getParty(),
                        'position' => $existingPolitician->getPosition(),
                        'score' => $existingPolitician->getScore(),
                        'status' => $existingPolitician->getStatus(),
                        'note' => $existingPolitician->getNote(),
                        'trending' => $existingPolitician->isTrending(),
                        'createdAt' => $existingPolitician->getCreatedAt()->format('c'),
                        'updatedAt' => $existingPolitician->getUpdatedAt()?->format('c')
                    ],
                    'created' => false
                ];
            }
            
            // Create new politician entity
            $politician = new Politician();
            $politician->setFullName($politicianData['fullName']);
            $politician->setCountry($politicianData['country']);
            $politician->setParty($politicianData['party'] ?? null);
            $politician->setPosition($politicianData['position'] ?? null);
            $politician->setScore($politicianData['score'] ?? 0);
            $politician->setStatus($politicianData['status'] ?? 'active');
            $politician->setNote($politicianData['note'] ?? null);
            $politician->setTrending(false);
            $politician->setUpdatedAt(new \DateTime());

            // Save to database
            $this->politicianRepository->save($politician, true);
            
            $this->logger->info("ShadyMeter: Created new politician - {$politician->getFullName()} from {$politician->getCountry()}");
            
            return [
                'success' => true,
                'message' => 'Politician added successfully',
                'politician_id' => $politician->getId(),
                'politician' => [
                    'id' => $politician->getId(),
                    'fullName' => $politician->getFullName(),
                    'country' => $politician->getCountry(),
                    'party' => $politician->getParty(),
                    'position' => $politician->getPosition(),
                    'score' => $politician->getScore(),
                    'status' => $politician->getStatus(),
                    'note' => $politician->getNote(),
                    'trending' => $politician->isTrending(),
                    'createdAt' => $politician->getCreatedAt()->format('c'),
                    'updatedAt' => $politician->getUpdatedAt()?->format('c')
                ],
                'created' => true
            ];
        } catch (\Exception $e) {
            $this->logger->error("ShadyMeter: Error adding politician: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if politician already exists in database
     */
    public function checkPoliticianExists(array $politicianData): array
    {
        try {
            $fullName = $politicianData['fullName'] ?? '';
            $country = $politicianData['country'] ?? '';
            
            if (empty($fullName) || empty($country)) {
                return [
                    'exists' => false,
                    'message' => 'Missing required data for check'
                ];
            }
            
            // Check if politician with same full name and country already exists
            $existingPolitician = $this->politicianRepository->findByFullNameAndCountry($fullName, $country);
            
            if ($existingPolitician) {
                return [
                    'exists' => true,
                    'success' => false,
                    'message' => 'Politician with this name and country already exists',
                    'politician_id' => $existingPolitician->getId(),
                    'politician' => [
                        'id' => $existingPolitician->getId(),
                        'fullName' => $existingPolitician->getFullName(),
                        'country' => $existingPolitician->getCountry(),
                        'party' => $existingPolitician->getParty(),
                        'position' => $existingPolitician->getPosition(),
                        'score' => $existingPolitician->getScore(),
                        'status' => $existingPolitician->getStatus(),
                        'note' => $existingPolitician->getNote(),
                        'trending' => $existingPolitician->isTrending(),
                        'createdAt' => $existingPolitician->getCreatedAt()->format('c'),
                        'updatedAt' => $existingPolitician->getUpdatedAt()?->format('c')
                    ],
                    'created' => false
                ];
            }
            
            return [
                'exists' => false,
                'message' => 'Politician not found in database'
            ];
        } catch (\Exception $e) {
            $this->logger->error("ShadyMeter: Error checking politician existence: " . $e->getMessage());
            return [
                'exists' => false,
                'message' => 'Error checking database'
            ];
        }
    }
} 