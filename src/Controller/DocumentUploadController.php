<?php

namespace App\Controller;

use App\Service\CompanyService;
use App\Service\FileUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/documents')]
class DocumentUploadController extends AbstractController
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private CompanyService $companyService
    ) {}

    #[Route('/company/{companyId}/id-copy', methods: ['POST'])]
    public function uploadIdCopy(int $companyId, Request $request): JsonResponse
    {
        // Check if company exists
        $company = $this->companyService->getCompanyById($companyId);
        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        // Get uploaded file
        $file = $request->files->get('document');
        if (!$file) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'No document file provided'
            ], 400);
        }

        try {
            // Upload the document
            $filename = $this->fileUploadService->uploadDocument($file, $companyId, 'id_copy');

            // Update company with new filename
            $this->companyService->updateCompany($companyId, [
                'id_copy' => $filename
            ]);

            return $this->json([
                'status' => 'OK',
                'message' => 'ID copy uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'url' => $this->fileUploadService->getDocumentUrl($filename)
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to upload document'
            ], 500);
        }
    }

    #[Route('/company/{companyId}/power-of-attorney', methods: ['POST'])]
    public function uploadPowerOfAttorney(int $companyId, Request $request): JsonResponse
    {
        // Check if company exists
        $company = $this->companyService->getCompanyById($companyId);
        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        // Get uploaded file
        $file = $request->files->get('document');
        if (!$file) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'No document file provided'
            ], 400);
        }

        try {
            // Upload the document
            $filename = $this->fileUploadService->uploadDocument($file, $companyId, 'power_of_attorney');

            // Update company with new filename
            $this->companyService->updateCompany($companyId, [
                'power_of_attorney' => $filename
            ]);

            return $this->json([
                'status' => 'OK',
                'message' => 'Power of attorney uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'url' => $this->fileUploadService->getDocumentUrl($filename)
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to upload document'
            ], 500);
        }
    }

    #[Route('/company/{companyId}/id-copy', methods: ['DELETE'])]
    public function deleteIdCopy(int $companyId): JsonResponse
    {
        // Check if company exists
        $company = $this->companyService->getCompanyById($companyId);
        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        $filename = $company->getIdCopy();
        if (!$filename) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'No ID copy document found'
            ], 404);
        }

        try {
            // Delete the file
            $this->fileUploadService->deleteDocument($filename);

            // Update company to remove filename
            $this->companyService->updateCompany($companyId, [
                'id_copy' => null
            ]);

            return $this->json([
                'status' => 'OK',
                'message' => 'ID copy deleted successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to delete document'
            ], 500);
        }
    }

    #[Route('/company/{companyId}/power-of-attorney', methods: ['DELETE'])]
    public function deletePowerOfAttorney(int $companyId): JsonResponse
    {
        // Check if company exists
        $company = $this->companyService->getCompanyById($companyId);
        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }

        $filename = $company->getPowerOfAttorney();
        if (!$filename) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'No power of attorney document found'
            ], 404);
        }

        try {
            // Delete the file
            $this->fileUploadService->deleteDocument($filename);

            // Update company to remove filename
            $this->companyService->updateCompany($companyId, [
                'power_of_attorney' => null
            ]);

            return $this->json([
                'status' => 'OK',
                'message' => 'Power of attorney deleted successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Failed to delete document'
            ], 500);
        }
    }

    #[Route('/company/{companyId}/id-copy', methods: ['GET'])]
    public function getIdCopy(int $companyId): JsonResponse|BinaryFileResponse
    {
        $company = $this->companyService->getCompanyById($companyId);
        if (!$company) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Company not found'
            ], 404);
        }
        $filename = $company->getIdCopy();
        if (!$filename) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'No ID copy document found'
            ], 404);
        }
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $filename;
        if (!file_exists($filePath)) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'File not found on server'
            ], 404);
        }
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );
        return $response;
    }
} 