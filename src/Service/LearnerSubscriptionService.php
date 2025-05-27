<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LearnerSubscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get learner's subscription by follow code
     * 
     * @param string $followCode
     * @return array
     * @throws NotFoundHttpException if learner not found
     */
    public function getSubscriptionByFollowCode(string $followCode): array
    {
        $learner = $this->entityManager->getRepository(Learner::class)
            ->findOneBy(['followMeCode' => $followCode]);

        if (!$learner) {
            throw new NotFoundHttpException('Learner not found');
        }

        return [
            'status' => 'OK',
            'subscription' => $learner->getSubscription() ?? 'free'
        ];
    }
}