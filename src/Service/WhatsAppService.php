<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppService
{
    private const API_BASE_URL = 'https://7103.api.greenapi.com';
    private const INSTANCE_ID = '7103294985';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiToken
    ) {}

    /**
     * Send WhatsApp message to a phone number
     */
    public function sendMessage(string $phoneNumber, string $message): bool
    {
        try {
            // Format phone number for WhatsApp (remove any non-digits and add @c.us)
            $formattedPhoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            $url = sprintf(
                '%s/waInstance%s/sendMessage/%s',
                self::API_BASE_URL,
                self::INSTANCE_ID,
                $this->apiToken
            );

            $payload = [
                'chatId' => $formattedPhoneNumber,
                'message' => $message
            ];

            $this->logger->info('Sending WhatsApp message', [
                'phone' => $phoneNumber,
                'formatted_phone' => $formattedPhoneNumber,
                'message_length' => strlen($message)
            ]);

            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getContent();

            if ($statusCode === 200) {
                $this->logger->info('WhatsApp message sent successfully', [
                    'phone' => $phoneNumber,
                    'response' => $responseContent
                ]);
                return true;
            } else {
                $this->logger->error('Failed to send WhatsApp message', [
                    'phone' => $phoneNumber,
                    'status_code' => $statusCode,
                    'response' => $responseContent
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while sending WhatsApp message', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send tender alert message
     */
    public function sendTenderAlert(string $phoneNumber, string $tenderNumber, string $description, string $category, string $province, string $closingDate): bool
    {
        $message = $this->formatTenderAlertMessage($tenderNumber, $description, $category, $province, $closingDate);
        return $this->sendMessage($phoneNumber, $message);
    }

    /**
     * Format phone number for WhatsApp API
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-digits
        $cleanNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // Add country code if not present (assuming South African numbers)
        if (strlen($cleanNumber) === 10 && str_starts_with($cleanNumber, '0')) {
            $cleanNumber = '27' . substr($cleanNumber, 1);
        } elseif (strlen($cleanNumber) === 9) {
            $cleanNumber = '27' . $cleanNumber;
        }
        
        return $cleanNumber . '@c.us';
    }

    /**
     * Format tender alert message
     */
    private function formatTenderAlertMessage(string $tenderNumber, string $description, string $category, string $province, string $closingDate): string
    {
        return sprintf(
            "🚨 *New Tender Alert* 🚨\n\n" .
            "📋 *Tender Number:* %s\n" .
            "📍 *Province:* %s\n" .
            "🏷️ *Category:* %s\n" .
            "📅 *Closing Date:* %s\n\n" .
            "📝 *Description:*\n%s\n\n" .
            "💡 Don't miss this opportunity!\n" .
            "_Powered by Tender Alert System_",
            $tenderNumber,
            $province,
            $category,
            $closingDate,
            $this->truncateDescription($description, 500)
        );
    }

    /**
     * Truncate description if too long
     */
    private function truncateDescription(string $description, int $maxLength = 500): string
    {
        if (strlen($description) <= $maxLength) {
            return $description;
        }
        
        return substr($description, 0, $maxLength) . '...';
    }

    /**
     * Send test message to verify WhatsApp API connectivity
     */
    public function sendTestMessage(string $phoneNumber): bool
    {
        $message = "🧪 *Test Message*\n\nThis is a test message from your Tender Alert System. If you receive this, your WhatsApp notifications are working correctly! ✅";
        return $this->sendMessage($phoneNumber, $message);
    }
}
