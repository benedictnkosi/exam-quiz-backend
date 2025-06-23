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
    private string $revenueCatApiKey;
    private LoggerInterface $logger;

    private const REVENUECAT_API_BASE_URL = 'https://api.revenuecat.com/v1';
    private const FREE_SUBSCRIPTION_IDENTIFIER = 'free';

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        string $revenueCatApiKey,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->revenueCatApiKey = $revenueCatApiKey;
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
            $learner->setSubscription("dimpo_gold");
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

    public function updateLearnerSubscriptionByEmail(string $email, ?string $subscription): Learner
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['email' => $email]);

        if (!$learner) {
            throw new \Exception("Learner not found with email: {$email}");
        }

        $learner->setSubscription($subscription);
        $this->entityManager->persist($learner);
        $this->entityManager->flush();

        return $learner;
    }

    public function updateRevenueCatSubscription(string $appUserId): array
    {
        $url = self::REVENUECAT_API_BASE_URL . '/subscribers/' . $appUserId;
        $headers = [
            'Authorization' => 'Bearer ' . $this->revenueCatApiKey,
            'Accept' => 'application/json',
        ];

        $this->logger->info("RevenueCat: Attempting to fetch subscription for appUser '{$appUserId}' from {$url}");

        try {
            $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);
            $data = $response->toArray();

            $resolvedLearnerIdentifier = null;
            $now = new DateTime('now', new \DateTimeZone('UTC'));
            $this->logger->info("RevenueCat: Current time (UTC for comparison): " . $now->format('Y-m-d H:i:sP'));
            $activeFreeEntitlementEncountered = false;
            $hasActivePaidSubscription = false;

            // First check entitlements
            if (isset($data['subscriber']['entitlements']) && is_array($data['subscriber']['entitlements'])) {
                $this->logger->info("RevenueCat: Checking entitlements for appUser '{$appUserId}'");
                $entitlements = $data['subscriber']['entitlements'];

                foreach ($entitlements as $entitlementData) {
                    if (!is_array($entitlementData) || !isset($entitlementData['product_identifier']) || !array_key_exists('expires_date', $entitlementData)) {
                        continue;
                    }

                    $productIdentifier = (string) $entitlementData['product_identifier'];
                    $expiresDateStr = $entitlementData['expires_date'];

                    $isActive = false;

                    if ($expiresDateStr === null) {
                        $isActive = true; // Entitlement never expires
                    } else {
                        $this->logger->info("RevenueCat: Entitlement expires date: {$expiresDateStr}");
                        try {
                            $expiresDate = new DateTime($expiresDateStr);
                            if ($expiresDate > $now) {
                                $isActive = true; // Entitlement expires in the future
                            }
                        } catch (\Exception $e) {
                            $this->logger->error("RevenueCat: Invalid date format for entitlement '{$productIdentifier}' for appUser '{$appUserId}'. Date: '{$expiresDateStr}'. Error: " . $e->getMessage());
                            continue;
                        }
                    }

                    if ($isActive) {
                        if ($productIdentifier === self::FREE_SUBSCRIPTION_IDENTIFIER) {
                            $activeFreeEntitlementEncountered = true;
                        } else {
                            $hasActivePaidSubscription = true;
                        }
                    }
                }
            }

            // If no active entitlements found, check subscriptions
            if (!$hasActivePaidSubscription && isset($data['subscriber']['subscriptions']) && is_array($data['subscriber']['subscriptions'])) {
                $this->logger->info("RevenueCat: No active entitlements found, checking subscriptions for appUser '{$appUserId}'");
                $subscriptions = $data['subscriber']['subscriptions'];

                if (count($subscriptions) == 0) {
                    $this->logger->info("RevenueCat: No subscriptions found for appUser '{$appUserId}'");
                }

                foreach ($subscriptions as $subscriptionData) {
                    $this->logger->info("RevenueCat: Subscription data: " . json_encode($subscriptionData));
                    if (!is_array($subscriptionData) || !isset($subscriptionData['expires_date'])) {
                        $this->logger->info("RevenueCat: Skipping subscription - missing required fields");
                        continue;
                    }

                    $expiresDateStr = $subscriptionData['expires_date'];

                    try {
                        $expiresDate = new DateTime($expiresDateStr);
                        if ($expiresDate > $now) {
                            $hasActivePaidSubscription = true;
                            $this->logger->info("RevenueCat: Found active paid subscription");
                            break;
                        } else {
                            $this->logger->info("RevenueCat: Subscription has expired");
                        }
                    } catch (\Exception $e) {
                        $this->logger->error("RevenueCat: Invalid date format for subscription for appUser '{$appUserId}'. Date: '{$expiresDateStr}'. Error: " . $e->getMessage());
                        continue;
                    }
                }
            }

            // If we found a paid subscription, set to dimpo_gold
            if ($hasActivePaidSubscription) {
                try {
                    $this->updateLearnerSubscriptionByUid($appUserId, 'dimpo_gold');

                    $this->logger->info("RevenueCat: Successfully updated learner ( ID: {$resolvedLearnerIdentifier}) for appUser '{$appUserId}' with dimpo_gold subscription.");
                    return [
                        'success' => true,
                        'subscription' => 'dimpo_gold'
                    ];
                } catch (\Exception $learnerUpdateException) {
                    $this->logger->error("RevenueCat: Failed to update learner (ID: {$resolvedLearnerIdentifier}) for appUser '{$appUserId}' with dimpo_gold subscription. Error: " . $learnerUpdateException->getMessage());
                    return [
                        'success' => false,
                        'error' => 'Learner update failed',
                        'details' => $learnerUpdateException->getMessage()
                    ];
                }
            }

            // If no paid subscription found, set to FREE_SUBSCRIPTION_IDENTIFIER
            try {
                $this->updateLearnerSubscriptionByUid($appUserId, self::FREE_SUBSCRIPTION_IDENTIFIER);

                if ($activeFreeEntitlementEncountered) {
                    $this->logger->info("RevenueCat: No paid subscription set. Set subscription to '" . self::FREE_SUBSCRIPTION_IDENTIFIER . "' for appUser '{$appUserId}' (ID: {$resolvedLearnerIdentifier}) based on an active free entitlement.");
                } else {
                    $this->logger->info("RevenueCat: No active paid or specific free entitlements found. Setting subscription to '" . self::FREE_SUBSCRIPTION_IDENTIFIER . "' by default for appUser '{$appUserId}' (ID: {$resolvedLearnerIdentifier}.");
                }
                return [
                    'success' => true,
                    'subscription' => self::FREE_SUBSCRIPTION_IDENTIFIER
                ];
            } catch (\Exception $learnerUpdateException) {
                $this->logger->error("RevenueCat: Failed to set subscription to '" . self::FREE_SUBSCRIPTION_IDENTIFIER . "' for appUser '{$appUserId}' (ID: {$resolvedLearnerIdentifier}, . Error: " . $learnerUpdateException->getMessage());
                return [
                    'success' => false,
                    'error' => 'Free subscription update failed',
                    'details' => $learnerUpdateException->getMessage()
                ];
            }

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