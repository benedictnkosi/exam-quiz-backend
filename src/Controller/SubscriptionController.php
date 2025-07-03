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
use Doctrine\ORM\EntityManagerInterface;

#[AsController]
class SubscriptionController extends AbstractController
{
    #[Route('/api/subscription/{projectName}', name: 'subscription_update', methods: ['POST'])]
    public function updateSubscription(
        Request $request,
        SubscriptionService $subscriptionService,
        SerializerInterface $serializer,
        string $projectName,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['event']['app_user_id'])) {
                throw new \Exception('app_user_id is required in the payload');
            }

            $firstAlias = $data['event']['aliases'][0];
            $appUserId = (strpos($firstAlias, ':') !== false || strpos($firstAlias, '$') !== false)
                ? $data['event']['aliases'][1]
                : $firstAlias;

            $result = $subscriptionService->updateRevenueCatSubscription($appUserId, $projectName);
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to update subscription');
            }
            
            // Find the learner to return
            $learner = $entityManager->getRepository(\App\Entity\Learner::class)->findOneBy(['uid' => $appUserId]);
            if (!$learner) {
                throw new \Exception('Learner not found after subscription update');
            }

            return $this->json($learner->getSubscription(), Response::HTTP_OK, [], ['groups' => 'learner:read']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}