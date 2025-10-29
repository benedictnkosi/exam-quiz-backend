<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YouTubeTranscriptService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, string $transcriptApiKey)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = $transcriptApiKey;
    }

    /**
     * Fetch transcript using transcriptapi.com.
     * Returns an associative array when format=json, or string when format=text.
     */
    public function fetchTranscript(string $videoIdOrUrl, string $format = 'json', bool $includeTimestamp = true, bool $sendMetadata = false)
    {
        $endpoint = 'https://transcriptapi.com/api/v2/youtube/transcript';
        $query = [
            'video_url' => $videoIdOrUrl,
            'format' => $format,
            'include_timestamp' => $includeTimestamp ? 'true' : 'false',
            'send_metadata' => $sendMetadata ? 'true' : 'false',
        ];

        $attempts = 0;
        $maxAttempts = 3; // per best practices, don't retry more than 2 times within ~3s; we add backoff
        $backoff = 1;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                $response = $this->httpClient->request('GET', $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Accept' => 'application/json',
                    ],
                    'query' => $query,
                    'timeout' => 60,
                ]);

                $status = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                $contentType = $headers['content-type'][0] ?? '';
                $content = $response->getContent(false);

                if ($status === 200) {
                    if ($format === 'text' || stripos($contentType, 'text/plain') !== false) {
                        return $content;
                    }
                    $data = json_decode($content, true);
                    return $data;
                }

                if ($status === 429) {
                    $retryAfter = (int)($headers['retry-after'][0] ?? $backoff);
                    $this->logger->warning('Transcript API rate limited, backing off', [
                        'attempt' => $attempts,
                        'retry_after' => $retryAfter,
                    ]);
                    sleep(max(1, $retryAfter));
                    continue;
                }

                if ($status >= 500 || $status === 402) {
                    $this->logger->warning('Transcript API transient/non-success', [
                        'status' => $status,
                        'body' => $content,
                        'attempt' => $attempts,
                    ]);
                    sleep($backoff);
                    $backoff = min(4, $backoff * 2);
                    continue;
                }

                // Non-retryable cases: 400, 401, 404
                $this->logger->warning('Transcript API non-retryable response', [
                    'status' => $status,
                    'body' => $content,
                ]);
                return null;
            } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
                $this->logger->error('Transcript API request failed', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempts,
                ]);
                sleep($backoff);
                $backoff = min(4, $backoff * 2);
            }
        }

        return null;
    }
}


