<?php

namespace App\Service;

use App\Entity\SmsMarketing;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsPortalService
{
    private const AUTH_URL = 'https://rest.smsportal.com/Authentication';
    private const API_URL = 'https://rest.smsportal.com/bulkmessages';

    public function __construct(
        private HttpClientInterface $httpClient,
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

            $response = $this->httpClient->request('GET', self::AUTH_URL, [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $result = json_decode($response->getContent(), true);
                $this->logger->debug('Authentication successful');
                return $result['token'] ?? null;
            }

            $this->logger->error('Authentication failed: ' . $response->getContent());
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
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $authToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messages' => [
                        [
                            'destination' => $smsMarketing->getPhoneNumber(),
                            'content' => $message,
                        ]
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('SMS sent successfully to: ' . $smsMarketing->getPhoneNumber());
                $smsMarketing->setLastSmsSentAt(new \DateTimeImmutable());
                $smsMarketing->setLastMessageSent($message);
                $this->entityManager->flush();
                return true;
            }

            $this->logger->error('Failed to send SMS: ' . $response->getContent());
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