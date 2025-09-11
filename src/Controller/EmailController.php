<?php

namespace App\Controller;

use App\Service\EmailService;
use App\Service\WhatsAppService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class EmailController extends AbstractController
{
    private EmailService $emailService;
    private WhatsAppService $whatsAppService;

    public function __construct(EmailService $emailService, WhatsAppService $whatsAppService)
    {
        $this->emailService = $emailService;
        $this->whatsAppService = $whatsAppService;
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
        $products = $data['products'] ?? null;
        $phoneNumber = $data['phone'] ?? null;

        if (!$to || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Send WhatsApp notification if phone number provided
        if (!empty($phoneNumber)) {
            $messageLines = [];
            $messageLines[] = "🛒 New Order Received";
            $messageLines[] = "Order #: " . (string)$orderNumber;
            $messageLines[] = "Total: R" . (string)$orderValue;
            if (!empty($products) && is_array($products)) {
                $messageLines[] = "Items:";
                foreach ($products as $product) {
                    $name = $product['name'] ?? 'Item';
                    $qty = $product['quantity'] ?? $product['qty'] ?? 1;
                    $price = $product['price'] ?? null;
                    $line = " - {$name} x{$qty}";
                    if ($price !== null) {
                        $line .= " @ R{$price}";
                    }
                    $messageLines[] = $line;
                }
            }
            $messageLines[] = "View order: " . (string)$orderUrl;
            $waMessage = implode("\n", $messageLines);
            $this->whatsAppService->sendMessage((string)$phoneNumber, $waMessage);
        }

        $success = $this->emailService->sendNewOrderEmail($to, $orderNumber, $orderValue, $orderUrl, $products);

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
        $products = $data['products'] ?? null;

        if (!$to || !$customerName || !$orderNumber || !$orderValue || !$orderUrl) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, customer_name, order_number, order_value, order_url.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendOrderCreatedEmail($to, $customerName, $orderNumber, $orderValue, $orderUrl, $products);

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

    #[Route('/api/send-appointment-booking-email', name: 'send_appointment_booking_email', methods: ['POST'])]
    public function sendAppointmentBookingEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $branch = $data['branch'] ?? null;
        $service = $data['service'] ?? null;
        $date = $data['date'] ?? null;
        $time = $data['time'] ?? null;
        $fullName = $data['full_name'] ?? null;
        $customerEmail = $data['customer_email'] ?? null;
        $phoneNumber = $data['phone_number'] ?? null;
        $notes = $data['notes'] ?? null;

        // Only service, date, full_name, and phone_number are required
        if (!$service || !$date || !$fullName || !$phoneNumber) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: service, date, full_name, phone_number.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendAppointmentBookingEmail($to, $branch, $service, $date, $time, $fullName, $customerEmail, $phoneNumber, $notes);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Appointment booking email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send appointment booking email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/send-contact-us-email', name: 'send_contact_us_email', methods: ['POST'])]
    public function sendContactUsEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $message = $data['message'] ?? null;
        $serviceInterested = $data['service_interested'] ?? null;
        $grade = $data['grade'] ?? null;
        $childName = $data['child_name'] ?? null;

        if (!$to || !$firstName || !$email || !$phone || !$message) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: to, first_name, email, phone, message.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->emailService->sendContactUsEmail($to, $firstName, $lastName, $email, $phone, $message, $serviceInterested, $grade, $childName);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Contact us email sent successfully.'
            ]);
        } else {
            return $this->json([
                'success' => false,
                'message' => 'Failed to send contact us email.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 