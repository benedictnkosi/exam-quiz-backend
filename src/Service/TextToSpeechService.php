<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TextToSpeechService
{
    private string $apiKey;
    private string $lecturesDirectory;

    public function __construct(ParameterBagInterface $params)
    {
        $this->apiKey = $params->get('openai_api_key');
        $this->lecturesDirectory = $params->get('kernel.project_dir') . '/public/assets/lectures';

        // Ensure the lectures directory exists
        if (!file_exists($this->lecturesDirectory)) {
            mkdir($this->lecturesDirectory, 0777, true);
        }
    }

    private function cleanLectureText(string $text): string
    {
        // Remove image search text
        $text = preg_replace('/\[Image Search:.*?\]/', '', $text);
        // Remove any extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public function convertToSpeech(string $text, string $filename): ?string
    {
        try {
            $client = HttpClient::create();

            // Clean the text before sending to OpenAI
            $cleanText = $this->cleanLectureText($text);

            $response = $client->request('POST', 'https://api.openai.com/v1/audio/speech', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'tts-1',
                    'input' => $cleanText,
                    'voice' => 'alloy',
                    'response_format' => 'opus',
                    'speed' => 1.0
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                error_log('OpenAI API error: ' . $response->getContent(false));
                return null;
            }

            $filePath = $this->lecturesDirectory . '/' . $filename . '.opus';
            file_put_contents($filePath, $response->getContent());

            return $filePath;
        } catch (TransportExceptionInterface $e) {
            error_log('Error converting text to speech: ' . $e->getMessage());
            return null;
        }
    }
}