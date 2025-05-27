<?php

namespace App\Controller;

use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/payments')]
class PaymentController extends AbstractController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    #[Route('', name: 'create_payment', methods: ['POST'])]
    public function createPayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['accountNumber'], $data['bankName'], $data['branchCode'], $data['price'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $payment = $this->paymentService->createPayment(
                $data['accountNumber'],
                $data['bankName'],
                $data['branchCode'],
                (float) $data['price']
            );

            return $this->json([
                'id' => $payment->getId(),
                'accountNumber' => $payment->getAccountNumber(),
                'bankName' => $payment->getBankName(),
                'branchCode' => $payment->getBranchCode(),
                'price' => $payment->getPrice(),
                'createdAt' => $payment->getCreatedAt()
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'get_payment', methods: ['GET'])]
    public function getPayment(int $id): JsonResponse
    {
        $payment = $this->paymentService->getPayment($id);
        if (!$payment) {
            return $this->json(['error' => 'Payment not found'], 404);
        }

        return $this->json([
            'id' => $payment->getId(),
            'accountNumber' => $payment->getAccountNumber(),
            'bankName' => $payment->getBankName(),
            'branchCode' => $payment->getBranchCode(),
            'price' => $payment->getPrice(),
            'createdAt' => $payment->getCreatedAt()
        ]);
    }

    #[Route('', name: 'get_all_payments', methods: ['GET'])]
    public function getAllPayments(): JsonResponse
    {
        $payments = $this->paymentService->getAllPayments();
        $result = array_map(function ($payment) {
            return [
                'id' => $payment->getId(),
                'accountNumber' => $payment->getAccountNumber(),
                'bankName' => $payment->getBankName(),
                'branchCode' => $payment->getBranchCode(),
                'price' => $payment->getPrice(),
                'createdAt' => $payment->getCreatedAt()
            ];
        }, $payments);

        return $this->json($result);
    }

    #[Route('/{id}', name: 'update_payment', methods: ['PUT'])]
    public function updatePayment(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $payment = $this->paymentService->updatePayment($id, $data);

        if (!$payment) {
            return $this->json(['error' => 'Payment not found'], 404);
        }

        return $this->json([
            'id' => $payment->getId(),
            'accountNumber' => $payment->getAccountNumber(),
            'bankName' => $payment->getBankName(),
            'branchCode' => $payment->getBranchCode(),
            'price' => $payment->getPrice(),
            'createdAt' => $payment->getCreatedAt()
        ]);
    }

    #[Route('/{id}', name: 'delete_payment', methods: ['DELETE'])]
    public function deletePayment(int $id): JsonResponse
    {
        $success = $this->paymentService->deletePayment($id);
        if (!$success) {
            return $this->json(['error' => 'Payment not found'], 404);
        }

        return $this->json(['message' => 'Payment deleted successfully']);
    }
}