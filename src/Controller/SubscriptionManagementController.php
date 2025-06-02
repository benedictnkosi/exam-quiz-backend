<?php

namespace App\Controller;

use App\Service\SubscriptionManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/subscriptions')]
class SubscriptionManagementController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionManagementService $subscriptionManagementService
    ) {
    }

    #[Route('', name: 'get_all_subscriptions', methods: ['GET'])]
    public function getAllSubscriptions(Request $request): JsonResponse
    {
        $subscriptions = $this->subscriptionManagementService->getAllSubscriptions();
        return $this->json([
            'status' => 'OK',
            'data' => $subscriptions
        ]);
    }

    #[Route('/add-gold', name: 'add_gold_subscription', methods: ['POST'])]
    public function addGoldSubscription(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['admin_uid'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Admin UID is required in request body'
            ], 401);
        }

        if (!isset($data['follow_me_code']) || !isset($data['end_date'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Missing required fields: follow_me_code and end_date'
            ], 400);
        }

        try {
            $endDate = new \DateTime($data['end_date']);
            $result = $this->subscriptionManagementService->addGoldSubscription(
                $data['follow_me_code'],
                $endDate,
                $data['admin_uid']
            );

            return $this->json($result, $result['status'] === 'OK' ? 200 : 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Invalid date format. Use YYYY-MM-DD'
            ], 400);
        }
    }
}