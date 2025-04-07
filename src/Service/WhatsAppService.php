<?php

namespace App\Service;

class WhatsAppService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = "https://7103.api.greenapi.com/waInstance7103220992/sendMessage/480b83071d8c40cea3a3baf9b4862ec956c94dbb653a4fea9d";
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