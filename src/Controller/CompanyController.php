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
        
        // Validate required fields using the service validation
        $validationErrors = $this->companyService->validateCompanyData($data);
        if (!empty($validationErrors)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Validation failed',
                'errors' => $validationErrors
            ], 400);
        }

        try {
            $company = $this->companyService->createCompany($data);
            
            return $this->json([
                'status' => 'OK',
                'message' => 'Company created successfully',
                'data' => $company->toArray()
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
            'data' => $company->toArray()
        ]);
    }

    #[Route('', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $companies = $this->companyService->getAllCompanies();
        
        $data = [];
        foreach ($companies as $company) {
            $data[] = $company->toArray();
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
                'data' => $company->toArray()
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

    #[Route('/enterprise-number/{enterpriseNumber}', methods: ['GET'])]
    public function getByEnterpriseNumber(string $enterpriseNumber): JsonResponse
    {
        $company = $this->companyService->getCompanyByEnterpriseNumber($enterpriseNumber);

        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        return $this->json([
            'status' => 'OK',
            'data' => $company->toArray()
        ]);
    }

    #[Route('/type/{enterpriseType}', methods: ['GET'])]
    public function getByEnterpriseType(string $enterpriseType): JsonResponse
    {
        $companies = $this->companyService->getCompaniesByEnterpriseType($enterpriseType);
        
        $data = [];
        foreach ($companies as $company) {
            $data[] = $company->toArray();
        }

        return $this->json([
            'status' => 'OK',
            'data' => $data
        ]);
    }

    #[Route('/status/{enterpriseStatus}', methods: ['GET'])]
    public function getByEnterpriseStatus(string $enterpriseStatus): JsonResponse
    {
        $companies = $this->companyService->getCompaniesByEnterpriseStatus($enterpriseStatus);
        
        $data = [];
        foreach ($companies as $company) {
            $data[] = $company->toArray();
        }

        return $this->json([
            'status' => 'OK',
            'data' => $data
        ]);
    }

    #[Route('/upsert', methods: ['POST'])]
    public function createOrUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validate required fields using the service validation
        $validationErrors = $this->companyService->validateCompanyData($data);
        if (!empty($validationErrors)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Validation failed',
                'errors' => $validationErrors
            ], 400);
        }

        try {
            $company = $this->companyService->createOrUpdateCompany($data);
            
            $action = $company->getId() ? 'updated' : 'created';
            
            return $this->json([
                'status' => 'OK',
                'message' => "Company {$action} successfully",
                'data' => $company->toArray(),
                'action' => $action
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to create or update company: ' . $e->getMessage()
            ], 500);
        }
    }
} 