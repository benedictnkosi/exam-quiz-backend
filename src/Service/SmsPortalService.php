<?php

namespace App\Service;

use App\Entity\SmsMarketing;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SmsPortalService
{
    private const AUTH_URL = 'https://rest.smsportal.com/Authentication';
    private const API_URL = 'https://rest.smsportal.com/bulkmessages';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $apiSecret
    ) {
    }

    private function getAuthToken(): ?string
    {
        try {
            $this->logger->debug('Starting authentication process');

            $credentials = base64_encode($this->apiKey . ':' . $this->apiSecret);
            
            $ch = curl_init(self::AUTH_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $credentials
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                $this->logger->debug('Authentication successful');
                return $result['token'] ?? null;
            }

            $this->logger->error('Authentication failed: ' . $response);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Authentication error: ' . $e->getMessage());
            return null;
        }
    }

    public function sendMarketingSms(SmsMarketing $smsMarketing, string $message): bool
    {
        try {
            $this->logger->debug('Starting SMS send process for number: ' . $smsMarketing->getPhoneNumber());
            $this->logger->debug('Message content: ' . $message);

            // Get authentication token
            $authToken = $this->getAuthToken();
            if (!$authToken) {
                $this->logger->error('Failed to obtain authentication token');
                return false;
            }

            // Send SMS
            $data = [
                'messages' => [
                    [
                        'destination' => $smsMarketing->getPhoneNumber(),
                        'content' => $message,
                    ]
                ]
            ];

            $ch = curl_init(self::API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $authToken,
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->logger->info('SMS sent successfully to: ' . $smsMarketing->getPhoneNumber());
                $smsMarketing->setLastSmsSentAt(new \DateTimeImmutable());
                $smsMarketing->setLastMessageSent($message);
                $this->entityManager->flush();
                return true;
            }

            $this->logger->error('Failed to send SMS: ' . $response);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error sending SMS: ' . $e->getMessage());
            return false;
        }
    }

    public function sendBulkMarketingSms(array $smsMarketingRecords, string $message): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($smsMarketingRecords as $record) {
            if ($this->sendMarketingSms($record, $message)) {
                $results['success'][] = $record->getPhoneNumber();
            } else {
                $results['failed'][] = $record->getPhoneNumber();
            }
        }

        return $results;
    }
}