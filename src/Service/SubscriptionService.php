<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use DateTime;

class SubscriptionService
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private string $revenueCatApiKey;

    private const REVENUECAT_API_BASE_URL = 'https://api.revenuecat.com/v1';
    private const FREE_SUBSCRIPTION_IDENTIFIER = 'free';

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient, string $revenueCatApiKey)
    {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->revenueCatApiKey = $revenueCatApiKey;
    }

    public function updateLearnerSubscriptionByUid(int $learnerUid, ?string $subscription): Learner
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);

        if (!$learner) {
            throw new \Exception("Learner not found with UID: {$learnerUid}");
        }

        $learner->setSubscription($subscription);
        $this->entityManager->persist($learner);
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

    public function updateRevenueCatSubscription(string $appUserId): ?string
    {
        $url = self::REVENUECAT_API_BASE_URL . '/subscribers/' . $appUserId;
        $headers = [
            'Authorization' => 'Bearer ' . $this->revenueCatApiKey,
            'Accept' => 'application/json',
        ];

        error_log("RevenueCat: Attempting to fetch subscription for appUser '{$appUserId}' from {$url}");

        try {
            $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);
            $data = $response->toArray();

            if (!isset($data['subscriber']['entitlements']) || !is_array($data['subscriber']['entitlements'])) {
                error_log("RevenueCat: No entitlements array found or it\'s not an array for appUser '{$appUserId}\'. Response: " . json_encode($data));
                return null;
            }

            error_log("RevenueCat: Entitlements array found for appUser '{$appUserId}\'. Response: " . json_encode($data['subscriber']['entitlements']));

            $resolvedLearnerIdentifier = null;
            $identifierType = null;

            // START OF EXISTING LEARNER IDENTIFICATION LOGIC (Copied from previous correct state)
            if (isset($data['subscriber']['other_aliases']) && is_array($data['subscriber']['other_aliases']) && !empty($data['subscriber']['other_aliases'])) {
                $firstAlias = $data['subscriber']['other_aliases'][0];
                if (is_numeric($firstAlias)) {
                    $resolvedLearnerIdentifier = (int) $firstAlias;
                    $identifierType = 'uid';
                    error_log("RevenueCat: Using first alias \'{$firstAlias}\' (UID: {$resolvedLearnerIdentifier}) for appUser \'{$appUserId}\'.");
                } else {
                    error_log("RevenueCat: First alias \'{$firstAlias}\' for appUser \'{$appUserId}\' is not numeric. Skipping as UID.");
                }
            }
            if ($identifierType === null) {
                if (is_numeric($appUserId)) {
                    $resolvedLearnerIdentifier = (int) $appUserId;
                    $identifierType = 'uid';
                    error_log("RevenueCat: Using input appUser ID \'{$appUserId}\' (UID: {$resolvedLearnerIdentifier}) as fallback for appUser \'{$appUserId}\'.");
                } else {
                    error_log("RevenueCat: Input appUser ID \'{$appUserId}\' is not numeric. Skipping as UID.");
                }
            }
            if ($identifierType === null && isset($data['subscriber']['original_app_user_id'])) {
                $rcOriginalAppUserId = $data['subscriber']['original_app_user_id'];
                if (is_numeric($rcOriginalAppUserId)) {
                    $resolvedLearnerIdentifier = (int) $rcOriginalAppUserId;
                    $identifierType = 'uid';
                    error_log("RevenueCat: Using RC original_app_user_id \'{$rcOriginalAppUserId}\' (UID: {$resolvedLearnerIdentifier}) as fallback for appUser \'{$appUserId}\'.");
                } else {
                    error_log("RevenueCat: RC original_app_user_id \'{$rcOriginalAppUserId}\' for appUser \'{$appUserId}\' is not numeric. Skipping as UID.");
                }
            }
            if ($identifierType === null) {
                if (isset($data['subscriber']['subscriber_attributes']['$email']['value'])) {
                    $learnerEmail = $data['subscriber']['subscriber_attributes']['$email']['value'];
                    $resolvedLearnerIdentifier = $learnerEmail;
                    $identifierType = 'email';
                    error_log("RevenueCat: No numeric UID found. Using email \'{$learnerEmail}\' for appUser \'{$appUserId}\'.");
                } else {
                    error_log("RevenueCat: No numeric UID and no email attribute found for appUser \'{$appUserId}\'. Cannot identify learner to update subscription.");
                    return null; // Cannot proceed without a resolved learner identifier
                }
            }
            // END OF EXISTING LEARNER IDENTIFICATION LOGIC

            $entitlements = $data['subscriber']['entitlements'];
            $now = new DateTime('now', new \DateTimeZone('UTC'));
            error_log("RevenueCat: Current time (UTC for comparison): " . $now->format('Y-m-d H:i:sP'));
            $activeFreeEntitlementEncountered = false;

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
                    error_log("RevenueCat: Entitlement expires date: {$expiresDateStr}");
                    try {
                        $expiresDate = new DateTime($expiresDateStr);
                        if ($expiresDate > $now) {
                            $isActive = true; // Entitlement expires in the future
                        }
                    } catch (\Exception $e) {
                        error_log("RevenueCat: Invalid date format for entitlement \'{$productIdentifier}\' for appUser \'{$appUserId}\'. Date: \'{$expiresDateStr}\'. Error: " . $e->getMessage());
                        continue;
                    }
                }

                if ($isActive) {
                    if ($productIdentifier !== self::FREE_SUBSCRIPTION_IDENTIFIER) {
                        // Active PAID entitlement found
                        try {
                            if ($identifierType === 'uid') {
                                $this->updateLearnerSubscriptionByUid($resolvedLearnerIdentifier, $productIdentifier);
                            } elseif ($identifierType === 'email') {
                                $this->updateLearnerSubscriptionByEmail($resolvedLearnerIdentifier, $productIdentifier);
                            }
                            error_log("RevenueCat: Successfully updated learner (Type: {$identifierType}, ID: {$resolvedLearnerIdentifier}) for appUser \'{$appUserId}\' with PAID subscription \'{$productIdentifier}\'.");
                            return $productIdentifier; // Paid subscription set, primary goal achieved.
                        } catch (\Exception $learnerUpdateException) {
                            error_log("RevenueCat: Failed to update learner (Type: {$identifierType}, ID: {$resolvedLearnerIdentifier}) for appUser \'{$appUserId}\' with PAID subscription \'{$productIdentifier}\'. Error: " . $learnerUpdateException->getMessage());
                            return null; // Critical failure updating a paid subscription.
                        }
                    } else {
                        // Active FREE entitlement found ($productIdentifier === self::FREE_SUBSCRIPTION_IDENTIFIER)
                        $activeFreeEntitlementEncountered = true;
                        // Do not return; continue checking for paid entitlements as they take precedence.
                    }
                }
            }

            // If loop completes, no active PAID subscription was successfully set and returned.
            // Now, set to FREE_SUBSCRIPTION_IDENTIFIER by default or if an active free one was seen.
            // $resolvedLearnerIdentifier is guaranteed to be non-null here due to the check after identification logic.
            try {
                if ($identifierType === 'uid') {
                    $this->updateLearnerSubscriptionByUid($resolvedLearnerIdentifier, self::FREE_SUBSCRIPTION_IDENTIFIER);
                } elseif ($identifierType === 'email') {
                    $this->updateLearnerSubscriptionByEmail($resolvedLearnerIdentifier, self::FREE_SUBSCRIPTION_IDENTIFIER);
                }

                if ($activeFreeEntitlementEncountered) {
                    error_log("RevenueCat: No paid subscription set. Set subscription to '" . self::FREE_SUBSCRIPTION_IDENTIFIER . "' for appUser \'{$appUserId}\' (ID: {$resolvedLearnerIdentifier}, Type: {$identifierType}) based on an active free entitlement.");
                } else {
                    error_log("RevenueCat: No active paid or specific free entitlements found. Setting subscription to '" . self::FREE_SUBSCRIPTION_IDENTIFIER . "' by default for appUser \'{$appUserId}\' (ID: {$resolvedLearnerIdentifier}, Type: {$identifierType}).");
                }
                return self::FREE_SUBSCRIPTION_IDENTIFIER;
            } catch (\Exception $learnerUpdateException) {
                error_log("RevenueCat: Failed to set subscription to '" . self::FREE_SUBSCRIPTION_IDENTIFIER . "' for appUser \'{$appUserId}\' (ID: {$resolvedLearnerIdentifier}, Type: {$identifierType}). Error: " . $learnerUpdateException->getMessage());
                return null;
            }

        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            error_log("RevenueCat: HTTP Client Exception for appUser \'{$appUserId}\'. Error: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("RevenueCat: General Exception for appUser \'{$appUserId}\'. Error: " . $e->getMessage());
            return null;
        }
    }
}