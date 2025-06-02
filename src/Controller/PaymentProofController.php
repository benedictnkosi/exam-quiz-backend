<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\PaymentProofService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/payment-proof')]
class PaymentProofController extends AbstractController
{
    public function __construct(
        private readonly PaymentProofService $paymentProofService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/upload', name: 'payment_proof_upload', methods: ['POST'])]
    public function uploadPaymentProof(Request $request): JsonResponse
    {
        try {
            $image = $request->files->get('proof_image');

            if (!$image) {
                return $this->json([
                    'status' => 'NOK',
                    'message' => 'No image file provided'
                ], 400);
            }

            $result = $this->paymentProofService->processPaymentProof(null, $image);

            if ($result['status'] === 'OK') {
                return $this->json($result);
            } else {
                return $this->json($result, 400);
            }

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error processing payment proof: ' . $e->getMessage()
            ], 500);
        }
    }
}