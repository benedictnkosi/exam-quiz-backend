<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class QuestionNumberExtractorService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
        $this->apiKey = $this->params->get('openai_api_key');
        $this->logger->info('OpenAI API Key: ' . substr($this->apiKey, 0, 8) . '...');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured');
        }
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
    }

    public function extractQuestionNumbers(string $fileId): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a data extraction assistant. Your job is to extract structured question numbers from documents. Respond only with a flat JSON array of strings. Do not include any explanations or formatting. Do not include keys or objects—only a raw JSON array.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'file',
                                'file' => [
                                    'file_id' => $fileId
                                ]
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Extract all question numberings from the exam paper and return them as a flat JSON array of strings. Include main questions (e.g., "1"), subquestions (e.g., "1.1", "2.1.1"), and sub-subquestions with letter labels (e.g., "2.1.1 (a)"). Return only the flat JSON array.'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $data = $response->toArray();

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response from OpenAI API');
        }

        $content = $data['choices'][0]['message']['content'];

        // Remove any whitespace and newlines from the content
        $content = trim($content);

        // Parse the JSON array
        $questionNumbers = json_decode($content, true);

        if (!is_array($questionNumbers)) {
            throw new \Exception('Failed to parse question numbers from API response');
        }

        // Validate that all elements are strings
        foreach ($questionNumbers as $number) {
            if (!is_string($number)) {
                throw new \Exception('Invalid question number format: all numbers must be strings');
            }
        }

        return $questionNumbers;
    }
}