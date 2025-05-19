<?php

namespace App\Controller;

use App\Service\SubscriptionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class SubscriptionController extends AbstractController
{
    #[Route('/api/subscription', name: 'subscription_update', methods: ['POST'])]
    public function updateSubscription(
        Request $request,
        SubscriptionService $subscriptionService,
        SerializerInterface $serializer
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['event']['app_user_id'])) {
                throw new \Exception('app_user_id is required in the payload');
            }

            $appUserId = $data['event']['app_user_id'];
            $subscription = $data['event'] ?? null;

            $learner = $subscriptionService->updateRevenueCatSubscription($appUserId);

            $json = $serializer->serialize($learner, 'json', ['groups' => 'learner:read']);
            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}