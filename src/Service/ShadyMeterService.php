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
        $politicians = $this->generatePoliticiansWithOpenAI($country);
        
        $savedPoliticians = [];
        foreach ($politicians as $politicianData) {
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
            
            $savedPoliticians[] = [
                'id' => $politician->getId(),
                'fullName' => $politician->getFullName(),
                'country' => $politician->getCountry(),
                'party' => $politician->getParty(),
                'position' => $politician->getPosition(),
                'score' => $politician->getScore(),
                'status' => $politician->getStatus(),
                'note' => $politician->getNote(),
                'created' => true
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
        $politicians = $this->generateTrendingPoliticiansWithOpenAI($country);
        
        $savedPoliticians = [];
        foreach ($politicians as $politicianData) {
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
            
            $savedPoliticians[] = [
                'id' => $politician->getId(),
                'fullName' => $politician->getFullName(),
                'country' => $politician->getCountry(),
                'party' => $politician->getParty(),
                'position' => $politician->getPosition(),
                'score' => $politician->getScore(),
                'status' => $politician->getStatus(),
                'note' => $politician->getNote(),
                'trending' => true,
                'created' => true
            ];
        }

        return $savedPoliticians;
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
        $scandals = $this->generateScandalsWithOpenAI($politician, $country);
        
        // Check for existing document with same politician and country
        $existingScandal = $this->scandalRepository->findByPoliticianAndCountry($politician, $country);
        
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
                'updated' => true
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
                'created' => true
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

        $input = "Provide the top 3 most significant corruption-related news stories from {$country} between {$startDateStr} and {$endDateStr}. Focus on political corruption, embezzlement, bribery, and accountability issues. For each story, return a JSON object with: title (headline), summary (2-3 sentences), impact (high/medium/low), and source (if known). Return as a JSON array. Only include real, documented events - do not invent stories.";

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

    private function generatePoliticiansWithOpenAI(string $country): array
    {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not set');
        }

        $input = "Give me a list of 20 politicians to watch in {$country} based on recent or historical corruption allegations. For each, return a JSON object with these keys: fullName, country, party, position, score (1-100, higher means more allegations), status (active, retired, or deceased), and note (a one-sentence reason). Return as a JSON array.";

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
        $input = "List the 10 most trending politicians in {$currentYear} in {$country} for corruption scandals in the past {$currentYear}. For each, return a JSON object with: fullName, country, party, position, score (1-100, higher means more trending), status (active, retired, or deceased), note (one-sentence reason), and a 'trending' field set to true. Return as a JSON array.";

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

        $input = "List recent or historical corruption-related scandals involving {$politician} from {$country}. For each scandal, include: a specific, real-world title (do not use generic titles like 'Scandal 1'), the year, a 1-sentence description with concrete details (do not use generic or placeholder descriptions), status (proven, under_investigation, cleared, or unresolved), and an impact score from 1–10. Do not invent scandals; only use real, documented events. If no scandals are found, return an empty array for scandals and a totalCorruptionScore of 0. Return the result as a JSON object with keys: politician, country, scandals (array), and totalCorruptionScore.";

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
        $input = "Write a detailed, journalistic article (300-500 words) about the following political scandal involving {$politician} from {$country}:\n\nTitle: {$scandalTitle}\nYear: {$scandalYear}\nDescription: {$scandalDescription}\n\nInclude background, timeline, impact, and aftermath. Use a neutral, factual tone. Return only the article text.";

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