<?php

namespace App\Controller;

use App\Service\LearnerSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LearnerSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly LearnerSubscriptionService $subscriptionService
    ) {
    }

    #[Route('/api/learner/subscription/{followCode}', name: 'get_learner_subscription', methods: ['GET'])]
    public function getSubscriptionByFollowCode(string $followCode): JsonResponse
    {
        try {
            $result = $this->subscriptionService->getSubscriptionByFollowCode($followCode);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => $e->getMessage()
            ], 404);
        }
    }
}