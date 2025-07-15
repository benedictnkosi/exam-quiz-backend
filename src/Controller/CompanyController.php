<?php

namespace App\Controller;

use App\Service\CompanyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/companies')]
class CompanyController extends AbstractController
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validate required fields
        $requiredFields = ['full_name', 'surname', 'id_number', 'residential_address', 'company_address', 'email_address', 'phone_number'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'status' => 'NOK',
                    'message' => "Field '$field' is required"
                ], 400);
            }
        }

        try {
            $company = $this->companyService->createCompany($data);
            
            return $this->json([
                'status' => 'OK',
                'message' => 'Company created successfully',
                'data' => [
                    'id' => $company->getId(),
                    'company_names' => $company->getCompanyNames(),
                    'full_name' => $company->getFullName(),
                    'surname' => $company->getSurname(),
                    'id_number' => $company->getIdNumber(),
                    'residential_address' => $company->getResidentialAddress(),
                    'company_address' => $company->getCompanyAddress(),
                    'email_address' => $company->getEmailAddress(),
                    'phone_number' => $company->getPhoneNumber(),
                    'id_copy' => $company->getIdCopy(),
                    'power_of_attorney' => $company->getPowerOfAttorney()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to create company: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $company = $this->companyService->getCompanyById($id);

        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        return $this->json([
            'status' => 'OK',
            'data' => [
                'id' => $company->getId(),
                'company_names' => $company->getCompanyNames(),
                'full_name' => $company->getFullName(),
                'surname' => $company->getSurname(),
                'id_number' => $company->getIdNumber(),
                'residential_address' => $company->getResidentialAddress(),
                'company_address' => $company->getCompanyAddress(),
                'email_address' => $company->getEmailAddress(),
                'phone_number' => $company->getPhoneNumber(),
                'id_copy' => $company->getIdCopy(),
                'power_of_attorney' => $company->getPowerOfAttorney()
            ]
        ]);
    }

    #[Route('', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $companies = $this->companyService->getAllCompanies();
        
        $data = [];
        foreach ($companies as $company) {
            $data[] = [
                'id' => $company->getId(),
                'company_names' => $company->getCompanyNames(),
                'full_name' => $company->getFullName(),
                'surname' => $company->getSurname(),
                'id_number' => $company->getIdNumber(),
                'residential_address' => $company->getResidentialAddress(),
                'company_address' => $company->getCompanyAddress(),
                'email_address' => $company->getEmailAddress(),
                'phone_number' => $company->getPhoneNumber(),
                'id_copy' => $company->getIdCopy(),
                'power_of_attorney' => $company->getPowerOfAttorney()
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
            $company = $this->companyService->updateCompany($id, $data);
            
            if (!$company) {
                return $this->json([
                    'status' => 'NOK',
                    'message' => 'Company not found'
                ], 404);
            }

            return $this->json([
                'status' => 'OK',
                'message' => 'Company updated successfully',
                'data' => [
                    'id' => $company->getId(),
                    'company_names' => $company->getCompanyNames(),
                    'full_name' => $company->getFullName(),
                    'surname' => $company->getSurname(),
                    'id_number' => $company->getIdNumber(),
                    'residential_address' => $company->getResidentialAddress(),
                    'company_address' => $company->getCompanyAddress(),
                    'email_address' => $company->getEmailAddress(),
                    'phone_number' => $company->getPhoneNumber(),
                    'id_copy' => $company->getIdCopy(),
                    'power_of_attorney' => $company->getPowerOfAttorney()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to update company: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $success = $this->companyService->deleteCompany($id);
        
        if (!$success) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        return $this->json([
            'status' => 'OK',
            'message' => 'Company deleted successfully'
        ]);
    }

    #[Route('/{id}/status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['status']) || empty($data['status'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => "Field 'status' is required"
            ], 400);
        }
        $company = $this->companyService->getCompanyById($id);
        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }
        $company->setStatus($data['status']);
        $this->getDoctrine()->getManager()->flush();
        return $this->json([
            'status' => 'OK',
            'message' => 'Company status updated successfully',
            'data' => [
                'id' => $company->getId(),
                'status' => $company->getStatus()
            ]
        ]);
    }
} 