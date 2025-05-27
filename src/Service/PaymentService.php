<?php

namespace App\Service;

use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createPayment(string $accountNumber, string $bankName, string $branchCode, float $price): Payment
    {
        $payment = new Payment();
        $payment->setAccountNumber($accountNumber);
        $payment->setBankName($bankName);
        $payment->setBranchCode($branchCode);
        $payment->setPrice($price);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function getPayment(int $id): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)->find($id);
    }

    public function getAllPayments(): array
    {
        return $this->entityManager->getRepository(Payment::class)->findAll();
    }

    public function updatePayment(int $id, array $data): ?Payment
    {
        $payment = $this->getPayment($id);
        if (!$payment) {
            return null;
        }

        if (isset($data['accountNumber'])) {
            $payment->setAccountNumber($data['accountNumber']);
        }
        if (isset($data['bankName'])) {
            $payment->setBankName($data['bankName']);
        }
        if (isset($data['branchCode'])) {
            $payment->setBranchCode($data['branchCode']);
        }
        if (isset($data['price'])) {
            $payment->setPrice($data['price']);
        }

        $this->entityManager->flush();
        return $payment;
    }

    public function deletePayment(int $id): bool
    {
        $payment = $this->getPayment($id);
        if (!$payment) {
            return false;
        }

        $this->entityManager->remove($payment);
        $this->entityManager->flush();
        return true;
    }
}