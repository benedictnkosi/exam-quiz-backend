<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use DateTime;

class SubscriptionService
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    private const REVENUECAT_API_BASE_URL = 'https://api.revenuecat.com/v2/';
    private const FREE_SUBSCRIPTION_IDENTIFIER = 'free';

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function updateLearnerSubscriptionByUid(string $learnerUid, ?string $subscription): Learner
    {
        // Find learner in the main learner table
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);

        if (!$learner) {
            throw new \Exception("Learner not found with UID: {$learnerUid}");
        }

        if ($subscription === 'free') {
            $learner->setSubscription($subscription);
        } else {
            $learner->setSubscription("Dimpo Pro");
        }

        $this->entityManager->persist($learner);

        // Create new subscription entry
        $subscriptionEntity = new \App\Entity\Subscription();
        $subscriptionEntity->setLearner($learner);
        $subscriptionEntity->setCreated(new DateTime());

        // Set end date based on subscription type
        if ($subscription !== self::FREE_SUBSCRIPTION_IDENTIFIER) {
            $endDate = new DateTime();
            if (str_contains($subscription, 'monthly')) {
                $endDate->modify('+30 days');
            } elseif (str_contains($subscription, 'annual')) {
                $endDate->modify('+365 days');
            } elseif (str_contains($subscription, 'weekly')) {
                $endDate->modify('+7 days');
            }
            $subscriptionEntity->setEndDate($endDate);
        }

        $subscriptionEntity->setPaymentDate(new DateTime());
        $subscriptionEntity->setAmount(amount: 0.00);

        $this->entityManager->persist($subscriptionEntity);
        $this->entityManager->flush();

        return $learner;
    }


    public function updateRevenueCatSubscription(string $appUserId, string $projectName): array
    {
        // Get project-specific API key and project ID from environment
        $projectApiKey = $_ENV["REVENUECAT_V2_{$projectName}_API_KEY"] ?? null;
        $projectId = $_ENV["REVENUECAT_V2_{$projectName}_PROJECT_ID"] ?? null;
        
        if (!$projectApiKey) {
            throw new \Exception("RevenueCat API key not found for project: {$projectName}");
        }
        
        if (!$projectId) {
            throw new \Exception("RevenueCat Project ID not found for project: {$projectName}");
        }
        
        $this->logger->info("RevenueCat: Using project-specific credentials for project: {$projectName}");
        
        $url = self::REVENUECAT_API_BASE_URL . 'projects/' . $projectId . '/customers/' . $appUserId . '/subscriptions';
        $this->logger->info("RevenueCat: URL: {$url}");
        $this->logger->info("RevenueCat: Project ID: {$projectId}");
        $this->logger->info("RevenueCat: App User ID: {$appUserId}");
        $this->logger->info("RevenueCat: Project API Key: {$projectApiKey}");
        $headers = [
            'Authorization' => 'Bearer ' . $projectApiKey,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);
            $data = $response->toArray();

            $hasEntitlement = false;
            if (isset($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
                foreach ($data['items'] as $item) {
                    if (isset($item['entitlements']['items']) && is_array($item['entitlements']['items']) && count($item['entitlements']['items']) > 0) {
                        $hasEntitlement = true;
                        break;
                    }
                }
            }

            $subscriptionType = $hasEntitlement ? 'pro' : 'free';
            $this->updateLearnerSubscriptionByUid($appUserId, $subscriptionType);

            return [
                'success' => true,
                'subscription' => $subscriptionType
            ];
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            $this->logger->error("RevenueCat: HTTP Client Exception for appUser '{$appUserId}'. Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'HTTP Client Error',
                'details' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            $this->logger->error("RevenueCat: General Exception for appUser '{$appUserId}'. Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'General Error',
                'details' => $e->getMessage()
            ];
        }
    }
}