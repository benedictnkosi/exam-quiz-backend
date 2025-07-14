<?php

namespace App\Controller;

use App\Service\BusinessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/businesses')]
class BusinessController extends AbstractController
{
    public function __construct(
        private BusinessService $businessService
    ) {}

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validate required fields
        if (!isset($data['business_name']) || empty($data['business_name'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Field business_name is required'
            ], 400);
        }

        if (!isset($data['company_id']) || empty($data['company_id'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Field company_id is required'
            ], 400);
        }

        try {
            $business = $this->businessService->createBusiness($data);
            
            return $this->json([
                'status' => 'OK',
                'message' => 'Business created successfully',
                'data' => [
                    'id' => $business->getId(),
                    'business_name' => $business->getBusinessName(),
                    'domain' => $business->getDomain(),
                    'email' => $business->getEmail(),
                    'contact_number' => $business->getContactNumber(),
                    'whatsapp_number' => $business->getWhatsappNumber(),
                    'company_id' => $business->getCompany()->getId()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to create business: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $business = $this->businessService->getBusinessById($id);

        if (!$business) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Business not found'
            ], 404);
        }

        return $this->json([
            'status' => 'OK',
            'data' => [
                'id' => $business->getId(),
                'business_name' => $business->getBusinessName(),
                'domain' => $business->getDomain(),
                'email' => $business->getEmail(),
                'contact_number' => $business->getContactNumber(),
                'whatsapp_number' => $business->getWhatsappNumber(),
                'company_id' => $business->getCompany()->getId()
            ]
        ]);
    }

    #[Route('', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $businesses = $this->businessService->getAllBusinesses();
        
        $data = [];
        foreach ($businesses as $business) {
            $data[] = [
                'id' => $business->getId(),
                'business_name' => $business->getBusinessName(),
                'domain' => $business->getDomain(),
                'email' => $business->getEmail(),
                'contact_number' => $business->getContactNumber(),
                'whatsapp_number' => $business->getWhatsappNumber(),
                'company_id' => $business->getCompany()->getId()
            ];
        }

        return $this->json([
            'status' => 'OK',
            'data' => $data
        ]);
    }

    #[Route('/company/{companyId}', methods: ['GET'])]
    public function getByCompany(int $companyId): JsonResponse
    {
        $businesses = $this->businessService->getBusinessesByCompany($companyId);
        
        $data = [];
        foreach ($businesses as $business) {
            $data[] = [
                'id' => $business->getId(),
                'business_name' => $business->getBusinessName(),
                'domain' => $business->getDomain(),
                'email' => $business->getEmail(),
                'contact_number' => $business->getContactNumber(),
                'whatsapp_number' => $business->getWhatsappNumber(),
                'company_id' => $business->getCompany()->getId()
            ];
        }

        return $this->json([
            'status' => 'OK',
            'data' => $data
        ]);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $business = $this->businessService->updateBusiness($id, $data);
            
            if (!$business) {
                return $this->json([
                    'status' => 'NOK',
                    'message' => 'Business not found'
                ], 404);
            }

            return $this->json([
                'status' => 'OK',
                'message' => 'Business updated successfully',
                'data' => [
                    'id' => $business->getId(),
                    'business_name' => $business->getBusinessName(),
                    'domain' => $business->getDomain(),
                    'email' => $business->getEmail(),
                    'contact_number' => $business->getContactNumber(),
                    'whatsapp_number' => $business->getWhatsappNumber(),
                    'company_id' => $business->getCompany()->getId()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to update business: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $success = $this->businessService->deleteBusiness($id);
        
        if (!$success) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Business not found'
            ], 404);
        }

        return $this->json([
            'status' => 'OK',
            'message' => 'Business deleted successfully'
        ]);
    }
} 