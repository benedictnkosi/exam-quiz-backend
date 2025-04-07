<?php

namespace App\Service;

class WhatsAppService
{
    private string $instanceId;
    private string $apiToken;
    private string $baseUrl;

    public function __construct(string $instanceId, string $apiToken)
    {
        $this->instanceId = $instanceId;
        $this->apiToken = $apiToken;
        $this->baseUrl = "https://7103.api.greenapi.com/waInstance{$this->instanceId}/sendMessage/{$this->apiToken}";
    }

    public function sendMessage(string $phoneNumber, string $message, bool $linkPreview = true): array
    {
        // Format phone number for WhatsApp (remove + and add @c.us)
        $chatId = str_replace('+', '', $phoneNumber) . '@c.us';

        $data = [
            'chatId' => $chatId,
            'message' => $message,
            'linkPreview' => $linkPreview
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);

        try {
            $response = file_get_contents($this->baseUrl, false, $context);
            return json_decode($response, true) ?? ['error' => 'Invalid response from API'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
} 