<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubscriptionStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function checkAndUpdateSubscriptions(): array
    {
        $results = [
            'checked' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => 0
        ];

        try {
            // Get all expired subscriptions
            $expiredSubscriptions = $this->entityManager->getRepository(Subscription::class)
                ->createQueryBuilder('s')
                ->where('s.endDate < :now')
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getResult();

            // Group subscriptions by learner
            $learnerSubscriptions = [];
            foreach ($expiredSubscriptions as $subscription) {
                $learnerId = $subscription->getLearner()->getId();
                if (!isset($learnerSubscriptions[$learnerId])) {
                    $learnerSubscriptions[$learnerId] = [];
                }
                $learnerSubscriptions[$learnerId][] = $subscription;
            }

            // Process each learner's subscriptions
            foreach ($learnerSubscriptions as $learnerId => $subscriptions) {
                $results['checked']++;
                $learner = $subscriptions[0]->getLearner();

                // Check if learner has any active subscriptions
                $hasActiveSubscription = $this->entityManager->getRepository(Subscription::class)
                    ->createQueryBuilder('s')
                    ->where('s.learner = :learner')
                    ->andWhere('s.endDate > :now')
                    ->setParameter('learner', $learner)
                    ->setParameter('now', new \DateTime())
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$hasActiveSubscription) {
                    // No active subscriptions, update learner to free
                    $learner->setSubscription('free');
                    $this->entityManager->persist($learner);
                    $results['updated']++;

                    $this->logger->info('Subscription status updated to free', [
                        'learner_id' => $learnerId,
                        'previous_subscription' => $learner->getSubscription()
                    ]);
                }

                // Delete subscriptions older than 7 days
                $sevenDaysAgo = new \DateTime('-7 days');
                foreach ($subscriptions as $subscription) {
                    if ($subscription->getEndDate() < $sevenDaysAgo) {
                        $this->entityManager->remove($subscription);
                        $results['deleted']++;

                        $this->logger->info('Deleted old subscription', [
                            'subscription_id' => $subscription->getId(),
                            'learner_id' => $learnerId,
                            'end_date' => $subscription->getEndDate()->format('Y-m-d')
                        ]);
                    }
                }
            }

            $this->entityManager->flush();

        } catch (\Exception $e) {
            $results['errors']++;
            $this->logger->error('Error checking subscriptions: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }
}