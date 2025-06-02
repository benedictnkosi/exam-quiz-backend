<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubscriptionManagementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getAllSubscriptions(): array
    {
        return $this->entityManager->getRepository(Subscription::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.learner', 'l')
            ->select('s.id, s.created, s.endDate, s.paymentDate, s.amount, l.id as learner_id, l.followMeCode')
            ->orderBy('s.created', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function addGoldSubscription(string $followMeCode, \DateTimeInterface $endDate, string $adminUid): array
    {
        try {
            // Validate admin UID
            $admin = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $adminUid, 'role' => 'admin']);

            if (!$admin) {
                return [
                    'status' => 'NOK',
                    'message' => 'Invalid admin UID or insufficient permissions'
                ];
            }

            // Find learner by follow me code
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['followMeCode' => $followMeCode]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found with follow me code: ' . $followMeCode
                ];
            }

            // Validate end date is not in the past
            $now = new \DateTime();
            if ($endDate < $now) {
                return [
                    'status' => 'NOK',
                    'message' => 'End date cannot be in the past'
                ];
            }

            // Check for overlapping subscriptions
            $overlappingSubscription = $this->entityManager->getRepository(Subscription::class)
                ->createQueryBuilder('s')
                ->where('s.learner = :learner')
                ->andWhere('s.endDate > :startDate')
                ->setParameter('learner', $learner)
                ->setParameter('startDate', $now)
                ->getQuery()
                ->getOneOrNullResult();

            if ($overlappingSubscription) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner already has an active subscription until ' . $overlappingSubscription->getEndDate()->format('Y-m-d')
                ];
            }

            // Create new subscription
            $subscription = new Subscription();
            $subscription->setCreated(new \DateTime());
            $subscription->setEndDate($endDate);
            $subscription->setLearner($learner);
            $subscription->setPaymentDate(new \DateTime());

            // Set amount based on subscription type
            if ($endDate->diff(new \DateTime())->days >= 365) {
                $subscription->setAmount(198);
                $learner->setSubscription('gold_annual');
            } else {
                $subscription->setAmount(28);
                $learner->setSubscription('gold_monthly');
            }

            $this->entityManager->persist($subscription);
            $this->entityManager->persist($learner);
            $this->entityManager->flush();

            $this->logger->info('Gold subscription added', [
                'learner_id' => $learner->getId(),
                'follow_me_code' => $followMeCode,
                'end_date' => $endDate->format('Y-m-d'),
                'subscription_type' => $learner->getSubscription(),
                'admin_uid' => $adminUid
            ]);

            return [
                'status' => 'OK',
                'message' => 'Subscription added successfully',
                'data' => [
                    'subscription_id' => $subscription->getId(),
                    'learner_id' => $learner->getId(),
                    'end_date' => $endDate->format('Y-m-d'),
                    'subscription_type' => $learner->getSubscription()
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error adding subscription: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'admin_uid' => $adminUid
            ]);

            return [
                'status' => 'NOK',
                'message' => 'Failed to add subscription: ' . $e->getMessage()
            ];
        }
    }
}