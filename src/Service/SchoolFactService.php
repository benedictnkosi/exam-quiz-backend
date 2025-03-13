<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SchoolFactService
{
    private string $openaiApiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
        $this->openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? '';
    }

    public function getSchoolFact(string $schoolName): array
    {
        try {
            if (!$this->openaiApiKey) {
                return [
                    'status' => 'NOK',
                    'message' => 'OpenAI API key is not configured'
                ];
            }

            $prompt = "Search for and provide an interesting fact about {$schoolName}, including historical milestones, notable alumni, unique traditions, or academic achievements. keep the response small, less than 20 words";

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 50
                ]
            ]);

            $data = $response->toArray();
            $fact = $data['choices'][0]['message']['content'] ?? null;

            if (!$fact) {
                return [
                    'status' => 'NOK',
                    'message' => 'Could not generate school fact'
                ];
            }

            return [
                'status' => 'OK',
                'fact' => trim($fact)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting school fact: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting school fact',
                'error' => $e->getMessage()
            ];
        }
    }
}