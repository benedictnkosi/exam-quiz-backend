<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HeyGenService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, string $heyGenApiKey)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = $heyGenApiKey;
    }

    /**
     * Create an avatar video using HeyGen API. Returns video_id on success or null on failure.
     */
    public function createAvatarVideoFromText(
        string $scriptText,
        string $avatarId = 'Daisy-inskirt-20220818',
        string $voiceId = '2d5b0e6cf36f460aa7fc47e3eee4ba54',
        int $width = 1080,
        int $height = 1920,
        bool $caption = true
    ): ?string
    {
        $endpoint = 'https://api.heygen.com/v2/video/generate';
        $payload = [
            'video_inputs' => [[
                'character' => [
                    'type' => 'avatar',
                    'avatar_id' => $avatarId,
                    'avatar_style' => 'normal',
                ],
                'voice' => [
                    'type' => 'text',
                    'input_text' => $scriptText,
                    'voice_id' => $voiceId,
                ],
                'background' => [
                    'type' => 'color',
                    'value' => '#000000',
                ],
            ]],
            'caption' => $caption,
            'dimension' => [
                'width' => $width,
                'height' => $height,
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 60,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
            if ($status === 200 && isset($data['data']['video_id'])) {
                $this->logger->info('HeyGen video created', ['video_id' => $data['data']['video_id']]);
                return $data['data']['video_id'];
            }
            $this->logger->warning('HeyGen create video non-success', ['status' => $status, 'response' => $data]);
        } catch (\Throwable $e) {
            $this->logger->error('HeyGen create video error', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Poll video status until completion or failure. Returns array with status and URLs when completed.
     */
    public function getVideoStatus(string $videoId): ?array
    {
        $endpoint = 'https://api.heygen.com/v1/video_status.get?video_id=' . urlencode($videoId);
        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                ],
                'timeout' => 30,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
            if ($status === 200 && isset($data['data'])) {
                return $data['data'];
            }
            $this->logger->warning('HeyGen status non-success', ['status' => $status, 'response' => $data]);
        } catch (\Throwable $e) {
            $this->logger->error('HeyGen status error', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Poll until the video is completed or timeout reached. Returns final status array or null on error/timeout.
     */
    public function waitForCompletion(string $videoId, int $timeoutSeconds = 300, int $pollIntervalSeconds = 5): ?array
    {
        $deadline = time() + max(1, $timeoutSeconds);
        while (time() < $deadline) {
            $status = $this->getVideoStatus($videoId);
            if (is_array($status)) {
                $state = $status['status'] ?? '';
                if ($state === 'completed' || $state === 'failed') {
                    return $status;
                }
            }
            sleep(max(1, $pollIntervalSeconds));
        }
        $this->logger->warning('HeyGen waitForCompletion timeout', ['video_id' => $videoId, 'timeout_sec' => $timeoutSeconds]);
        return null;
    }

    /**
     * Download a completed video URL to a local file path. Returns saved path or null.
     */
    public function downloadVideo(string $videoUrl, string $targetPath): ?string
    {
        try {
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $response = $this->httpClient->request('GET', $videoUrl, [ 'timeout' => 120 ]);
            $content = $response->getContent(false);
            if ($response->getStatusCode() === 200 && $content !== '') {
                file_put_contents($targetPath, $content);
                $this->logger->info('HeyGen video downloaded', ['path' => $targetPath]);
                return $targetPath;
            }
            $this->logger->warning('Failed to download HeyGen video', ['status' => $response->getStatusCode()]);
        } catch (\Throwable $e) {
            $this->logger->error('HeyGen download error', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * List available avatars from HeyGen.
     * Returns array of avatars or null on error.
     */
    public function listAvatars(): ?array
    {
        $endpoint = 'https://api.heygen.com/v2/avatars';
        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
            if ($status === 200) {
                $this->logger->info('HeyGen list avatars response', ['response' => $data]);
                return $data;
            }
            $this->logger->warning('HeyGen list avatars non-success', ['status' => $status, 'response' => $data]);
        } catch (\Throwable $e) {
            $this->logger->error('HeyGen list avatars error', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * List available voices from HeyGen.
     * Returns array of voices or null on error.
     */
    public function listVoices(): ?array
    {
        $endpoint = 'https://api.heygen.com/v2/voices';
        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
            if ($status === 200) {
                $this->logger->info('HeyGen list voices response', ['response' => $data]);
                return $data;
            }
            $this->logger->warning('HeyGen list voices non-success', ['status' => $status, 'response' => $data]);
        } catch (\Throwable $e) {
            $this->logger->error('HeyGen list voices error', ['error' => $e->getMessage()]);
        }
        return null;
    }
}


