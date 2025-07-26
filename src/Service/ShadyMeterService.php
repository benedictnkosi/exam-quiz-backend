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

    public function generateTrendingPoliticians(string $country): array
    {
        // Check if we already have trending politicians for this country created today
        $existingTrending = $this->politicianRepository->findTrendingByCountryAndToday($country);
        
        if (!empty($existingTrending)) {
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

        // Generate the article using OpenAI
        $article = $this->generateArticleWithOpenAI($politician, $country, $scandal);
        
        // Find the scandal document and update it with the article
        $scandalEntity = $this->scandalRepository->findByPoliticianAndCountry($politician, $country);
        if (!$scandalEntity) {
            throw new \Exception('No scandal document found for this politician');
        }

        // Get the scandals array and find the specific scandal
        $scandals = $scandalEntity->getScandals();
        $scandalFound = false;
        
        foreach ($scandals as &$s) {
            if ($s['title'] === $scandal['title'] && (string)$s['year'] === (string)$scandal['year']) {
                $s['article'] = $article;
                $scandalFound = true;
                break;
            }
        }
        
        if (!$scandalFound) {
            throw new \Exception('Scandal not found in document');
        }

        // Update the entity and save to database
        $scandalEntity->setScandals($scandals);
        $scandalEntity->setUpdatedAt(new \DateTime());
        $this->scandalRepository->save($scandalEntity, true);
        
        return [
            'article' => $article
        ];
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
            'createdAt' => $politician->getCreatedAt(),
            'updatedAt' => $politician->getUpdatedAt()
        ];
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

        $input = "🎪 Welcome to the 'Greatest Hits' collection of {$politician} from {$country}! Let's dig up all the juicy corruption-related scandals that made this political superstar famous (or should we say infamous? 😂). For each scandal, include: a specific, real-world title (make it catchy and slightly sarcastic - no boring 'Scandal 1' titles!), the year, a 1-sentence description with concrete details and a dash of wit (no generic descriptions - we want the tea! ☕), status (proven, under_investigation, cleared, or unresolved), and an impact score from 1–10 (their 'shadiness level'! 🌚). Do not invent scandals; only use real, documented events. If no scandals are found, return an empty array for scandals and a totalCorruptionScore of 0 (maybe they're just really good at hiding things! 🤷‍♂️). Return the result as a JSON object with keys: politician, country, scandals (array), and totalCorruptionScore.";

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
                    'impactScore' => $impactScore
                ];
            }, $result['scandals']),
            'totalCorruptionScore' => $result['totalCorruptionScore']
        ];

        return $processedResult;
    }

    private function generateArticleWithOpenAI(string $politician, string $country, array $scandal): string
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $scandalTitle = $scandal['title'] ?? 'Unknown Scandal';
        $scandalYear = $scandal['year'] ?? 'Unknown Year';
        $scandalDescription = $scandal['description'] ?? '';

        // Generate article prompt matching the route.ts pattern
        $input = "🎭 Comedy journalist extraordinaire, it's time to write a hilariously detailed article (300-500 words) about this political scandal involving {$politician} from {$country}! This is the kind of story that writes itself - and boy, does it have plot twists! 😂\n\nTitle: {$scandalTitle}\nYear: {$scandalYear}\nDescription: {$scandalDescription}\n\nInclude background, timeline, impact, and aftermath - but make it entertaining! Use a witty, satirical tone that makes readers laugh while learning about serious issues. Think 'The Onion' meets '60 Minutes'! Return only the article text.";

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

        $article = trim($content);
        if (empty($article)) {
            throw new \Exception('No article generated');
        }

        return $article;
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
} 