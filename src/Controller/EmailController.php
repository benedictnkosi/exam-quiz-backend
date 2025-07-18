<?php

namespace App\Controller;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class EmailController extends AbstractController
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    #[Route('/api/send-email', name: 'send_email', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? null;

        if (!$to || !$subject || !$body) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, subject, body.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendEmail($to, $subject, $body);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-new-order-email', name: 'send_new_order_email', methods: ['POST'])]
    public function sendNewOrderEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $orderValue = $data['order_value'] ?? null;
        $orderUrl = $data['order_url'] ?? null;

        if (!$to || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendNewOrderEmail($to, $orderNumber, $orderValue, $orderUrl);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'New order email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send new order email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-order-cancelled-email-with-recipient', name: 'send_order_cancelled_email_with_recipient', methods: ['POST'])]
    public function sendOrderCancelledEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $recipientName = $data['recipient_name'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $orderValue = $data['order_value'] ?? null;
        $orderUrl = $data['order_url'] ?? null;

        if (!$to || !$recipientName || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, recipient_name, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendOrderCancelledEmail($to, $recipientName, $orderNumber, $orderValue, $orderUrl);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Order cancelled email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send order cancelled email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-order-created-email', name: 'send_order_created_email', methods: ['POST'])]
    public function sendOrderCreatedEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $customerName = $data['customer_name'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $orderValue = $data['order_value'] ?? null;
        $orderUrl = $data['order_url'] ?? null;

        if (!$to || !$customerName || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, customer_name, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendOrderCreatedEmail($to, $customerName, $orderNumber, $orderValue, $orderUrl);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Order created email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send order created email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-order-processing-email', name: 'send_order_processing_email', methods: ['POST'])]
    public function sendOrderProcessingEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $recipientName = $data['recipient_name'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $orderValue = $data['order_value'] ?? null;
        $orderUrl = $data['order_url'] ?? null;

        if (!$to || !$recipientName || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, recipient_name, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendOrderProcessingEmail($to, $recipientName, $orderNumber, $orderValue, $orderUrl);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Order processing email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send order processing email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-order-shipped-email', name: 'send_order_shipped_email', methods: ['POST'])]
    public function sendOrderShippedEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $recipientName = $data['recipient_name'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $orderValue = $data['order_value'] ?? null;
        $orderUrl = $data['order_url'] ?? null;
        $trackingUrl = $data['tracking_url'] ?? null;

        if (!$to || !$recipientName || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, recipient_name, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendOrderShippedEmail($to, $recipientName, $orderNumber, $orderValue, $orderUrl, $trackingUrl);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Order shipped email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send order shipped email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-order-complete-email', name: 'send_order_complete_email', methods: ['POST'])]
    public function sendOrderCompleteEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $recipientName = $data['recipient_name'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $orderValue = $data['order_value'] ?? null;
        $orderUrl = $data['order_url'] ?? null;

        if (!$to || !$recipientName || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, recipient_name, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendOrderCompleteEmail($to, $recipientName, $orderNumber, $orderValue, $orderUrl);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Order complete email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send order complete email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 