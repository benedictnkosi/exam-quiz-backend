<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport;
use Twig\Environment;

class EmailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $smtpPassword;
    private Environment $twig;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger, string $smtpPassword, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->smtpPassword = $smtpPassword;
        $this->twig = $twig;
    }

    /**
     * Send an email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML allowed)
     * @param string|null $from Sender email address (optional)
     * @return bool Success
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $from = null): bool
    {
        $this->logger->info('SMTP password used for email: ' . $this->smtpPassword);
        try {
            $dsn = sprintf(
                'smtp://%s:%s@mail.spacemail.com:465?encryption=ssl',
                urlencode('info@dimpohosting.co.za'),
                urlencode($this->smtpPassword)
            );
            $transport = Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new Email())
                ->from($from ?? 'info@dimpohosting.co.za')
                ->to($to)
                ->subject($subject)
                ->html($body);

            $mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a new order email using the NewOrderReceived.html.twig template
     *
     * @param string $to
     * @param string $orderNumber
     * @param string $orderValue
     * @param string $orderUrl
     * @param array|null $products
     * @param string|null $from
     * @return bool
     */
    public function sendNewOrderEmail(string $to, string $orderNumber, string $orderValue, string $orderUrl, ?array $products = null, ?string $from = null): bool
    {
        $subject = 'New Order Received';
        $body = $this->twig->render('EmailTemplates/NewOrderReceived.html.twig', [
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'order_url' => $orderUrl,
            'products' => $products,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }

    /**
     * Send an order cancelled email using the OrderCancelled.html.twig template
     *
     * @param string $to
     * @param string $recipientName
     * @param string $orderNumber
     * @param string $orderValue
     * @param string $orderUrl
     * @param string|null $from
     * @return bool
     */
    public function sendOrderCancelledEmail(string $to, string $recipientName, string $orderNumber, string $orderValue, string $orderUrl, ?string $from = null): bool
    {
        $subject = 'Order Cancelled';
        $body = $this->twig->render('EmailTemplates/OrderCancelled.html.twig', [
            'recipient_name' => $recipientName,
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'order_url' => $orderUrl,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }

    /**
     * Send an order created email to the customer using the OrderCreated.html.twig template
     *
     * @param string $to
     * @param string $customerName
     * @param string $orderNumber
     * @param string $orderValue
     * @param string $orderUrl
     * @param array|null $products
     * @param string|null $from
     * @return bool
     */
    public function sendOrderCreatedEmail(string $to, string $customerName, string $orderNumber, string $orderValue, string $orderUrl, ?array $products = null, ?string $from = null): bool
    {
        $subject = 'Your Order Has Been Created';
        $body = $this->twig->render('EmailTemplates/OrderCreated.html.twig', [
            'customer_name' => $customerName,
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'order_url' => $orderUrl,
            'products' => $products,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }

    /**
     * Send an order processing email using the OrderProcessing.html.twig template
     *
     * @param string $to
     * @param string $recipientName
     * @param string $orderNumber
     * @param string $orderValue
     * @param string $orderUrl
     * @param string|null $from
     * @return bool
     */
    public function sendOrderProcessingEmail(string $to, string $recipientName, string $orderNumber, string $orderValue, string $orderUrl, ?string $from = null): bool
    {
        $subject = 'Your Order is Being Processed';
        $body = $this->twig->render('EmailTemplates/OrderProcessing.html.twig', [
            'recipient_name' => $recipientName,
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'order_url' => $orderUrl,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }

    /**
     * Send an order shipped email using the OrderShipped.html.twig template
     *
     * @param string $to
     * @param string $recipientName
     * @param string $orderNumber
     * @param string $orderValue
     * @param string $orderUrl
     * @param string|null $trackingUrl
     * @param string|null $from
     * @return bool
     */
    public function sendOrderShippedEmail(string $to, string $recipientName, string $orderNumber, string $orderValue, string $orderUrl, ?string $trackingUrl = null, ?string $from = null): bool
    {
        $subject = 'Your Order Has Shipped!';
        $body = $this->twig->render('EmailTemplates/OrderShipped.html.twig', [
            'recipient_name' => $recipientName,
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'order_url' => $orderUrl,
            'tracking_url' => $trackingUrl,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }

    /**
     * Send an order complete email using the OrderComplete.html.twig template
     *
     * @param string $to
     * @param string $recipientName
     * @param string $orderNumber
     * @param string $orderValue
     * @param string $orderUrl
     * @param string|null $from
     * @return bool
     */
    public function sendOrderCompleteEmail(string $to, string $recipientName, string $orderNumber, string $orderValue, string $orderUrl, ?string $from = null): bool
    {
        $subject = 'Your Order is Complete!';
        $body = $this->twig->render('EmailTemplates/OrderComplete.html.twig', [
            'recipient_name' => $recipientName,
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'order_url' => $orderUrl,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }

    /**
     * Send an appointment booking email using the AppointmentBooking.html.twig template
     *
     * @param string|null $to
     * @param string|null $branch
     * @param string $service
     * @param string $date
     * @param string|null $time
     * @param string $fullName
     * @param string|null $customerEmail
     * @param string $phoneNumber
     * @param string|null $notes
     * @param string|null $from
     * @return bool
     */
    public function sendAppointmentBookingEmail(?string $to, ?string $branch, string $service, string $date, ?string $time, string $fullName, ?string $customerEmail, string $phoneNumber, ?string $notes = null, ?string $from = null): bool
    {
        $subject = 'New Appointment Booking';
        $body = $this->twig->render('EmailTemplates/AppointmentBooking.html.twig', [
            'branch' => $branch,
            'service' => $service,
            'date' => $date,
            'time' => $time,
            'full_name' => $fullName,
            'customer_email' => $customerEmail,
            'phone_number' => $phoneNumber,
            'notes' => $notes,
        ]);
        return $this->sendEmail($to ?? 'admin@example.com', $subject, $body, $from);
    }

    /**
     * Send a contact us email using the ContactUs.html.twig template
     *
     * @param string $to
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phone
     * @param string $message
     * @param string|null $serviceInterested
     * @param string|null $from
     * @return bool
     */
    public function sendContactUsEmail(string $to, string $firstName, string $lastName, string $email, string $phone, string $message, ?string $serviceInterested = null, ?string $from = null): bool
    {
        $subject = 'New Contact Form Submission';
        $body = $this->twig->render('EmailTemplates/ContactUs.html.twig', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'service_interested' => $serviceInterested,
            'message' => $message,
        ]);
        return $this->sendEmail($to, $subject, $body, $from);
    }
} 