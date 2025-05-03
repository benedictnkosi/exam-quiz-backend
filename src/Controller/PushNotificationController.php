<?php

namespace App\Controller;

use App\Service\PushNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/push-notifications')]
class PushNotificationController extends AbstractController
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService
    ) {
    }

    #[Route('/update-token', name: 'update_push_token', methods: ['POST'])]
    public function updatePushToken(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['uid']) || !isset($data['push_token'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Missing required parameters: uid and push_token'
            ], 400);
        }

        $pushToken = str_contains($data['push_token'], 'disable') ? '' : $data['push_token'];

        $result = $this->pushNotificationService->updatePushToken(
            $data['uid'],
            $pushToken
        );

        return $this->json($result);
    }

    #[Route('/send-inactive-users', name: 'send_inactive_users_notifications', methods: ['POST'])]
    public function sendInactiveUsersNotifications(): JsonResponse
    {
        try {
            $result = $this->pushNotificationService->sendNotificationsToInactiveUsers();
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/send', name: 'send_notifications', methods: ['POST'])]
    public function sendNotifications(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['message']) || !isset($data['pushTokens']) || !is_array($data['pushTokens'])) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Invalid request data. Required fields: message, pushTokens'
            ], 400);
        }

        $title = $data['title'] ?? 'New Message';
        $result = $this->pushNotificationService->sendNotificationsToTokens(
            $data['pushTokens'],
            $data['message'],
            $title
        );

        return new JsonResponse($result, $result['status'] === 'OK' ? 200 : 500);
    }

    #[Route('/new-thread', name: 'send_new_thread_notification', methods: ['POST'])]
    public function sendNewThreadNotification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['subject_name']) || !isset($data['thread_title']) || !isset($data['uid'])) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Invalid request data. Required fields: subject_name, thread_title, uid'
            ], 400);
        }

        $result = $this->pushNotificationService->a(
            $data['subject_name'],
            $data['thread_title'],
            $data['uid'],
            $data['grade'] ?? null
        );

        return new JsonResponse($result, $result['status'] === 'OK' ? 200 : 500);
    }
}