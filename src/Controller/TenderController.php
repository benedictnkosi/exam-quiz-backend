<?php

namespace App\Controller;

use App\Entity\Tender;
use App\Repository\TenderRepository;
use App\Service\TenderService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tenders')]
class TenderController extends AbstractController
{
    public function __construct(
        private TenderService $tenderService,
        private TenderRepository $tenderRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'tender_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $category = $request->query->get('category');
        $province = $request->query->get('province');
        $organOfState = $request->query->get('organOfState');

        $tenders = $this->tenderService->getTenders($page, $limit, $category, $province, $organOfState);

        return $this->json([
            'success' => true,
            'data' => $tenders,
            'message' => 'Tenders retrieved successfully'
        ]);
    }

    #[Route('/basic', name: 'tender_index_basic', methods: ['GET'])]
    public function indexBasic(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $category = $request->query->get('category');
        $province = $request->query->get('province');
        $organOfState = $request->query->get('organOfState');

        $tenders = $this->tenderService->getTenders($page, $limit, $category, $province, $organOfState);
        
        // Keep successfulBidders in the response (no filtering needed)
        // The tenders array already includes successfulBidders from the service

        return $this->json([
            'success' => true,
            'data' => $tenders,
            'message' => 'Tenders details retrieved successfully'
        ]);
    }

    #[Route('/{id}', name: 'tender_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $tender = $this->tenderRepository->find($id);

        if (!$tender) {
            return $this->json([
                'success' => false,
                'message' => 'Tender not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $tender->toArray(),
            'message' => 'Tender retrieved successfully'
        ]);
    }

    #[Route('/{id}/basic', name: 'tender_show_basic', methods: ['GET'])]
    public function showBasic(int $id): JsonResponse
    {
        $tender = $this->tenderRepository->find($id);

        if (!$tender) {
            return $this->json([
                'success' => false,
                'message' => 'Tender not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $tender->toArrayWithBidders(),
            'message' => 'Tender details retrieved successfully'
        ]);
    }

    #[Route('', name: 'tender_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate tender data including tender number uniqueness
            $validationErrors = $this->tenderService->validateTenderData($data);
            if (!empty($validationErrors)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationErrors
                ], Response::HTTP_BAD_REQUEST);
            }

            $tender = $this->tenderService->createTender($data);

            return $this->json([
                'success' => true,
                'data' => $tender->toArray(),
                'message' => 'Tender created successfully'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error creating tender: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'tender_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $tender = $this->tenderRepository->find($id);

        if (!$tender) {
            return $this->json([
                'success' => false,
                'message' => 'Tender not found'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ], Response::HTTP_BAD_REQUEST);
            }

            $updatedTender = $this->tenderService->updateTender($tender, $data);

            return $this->json([
                'success' => true,
                'data' => $updatedTender->toArray(),
                'message' => 'Tender updated successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error updating tender: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'tender_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $tender = $this->tenderRepository->find($id);

        if (!$tender) {
            return $this->json([
                'success' => false,
                'message' => 'Tender not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->tenderService->deleteTender($tender);

        return $this->json([
            'success' => true,
            'message' => 'Tender deleted successfully'
        ]);
    }

    #[Route('/category/{category}', name: 'tender_by_category', methods: ['GET'])]
    public function getByCategory(string $category): JsonResponse
    {
        $tenders = $this->tenderRepository->findByCategory($category);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($tender) => $tender->toArray(), $tenders),
            'message' => 'Tenders by category retrieved successfully'
        ]);
    }

    #[Route('/province/{province}', name: 'tender_by_province', methods: ['GET'])]
    public function getByProvince(string $province): JsonResponse
    {
        $tenders = $this->tenderRepository->findByProvince($province);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($tender) => $tender->toArray(), $tenders),
            'message' => 'Tenders by province retrieved successfully'
        ]);
    }

    #[Route('/active', name: 'tender_active', methods: ['GET'])]
    public function getActiveTenders(): JsonResponse
    {
        $tenders = $this->tenderRepository->findActiveTenders();

        return $this->json([
            'success' => true,
            'data' => array_map(fn($tender) => $tender->toArray(), $tenders),
            'message' => 'Active tenders retrieved successfully'
        ]);
    }

    #[Route('/awarded', name: 'tender_awarded', methods: ['GET'])]
    public function getAwardedTenders(): JsonResponse
    {
        $tenders = $this->tenderRepository->findAwardedTenders();

        return $this->json([
            'success' => true,
            'data' => array_map(fn($tender) => $tender->toArray(), $tenders),
            'message' => 'Awarded tenders retrieved successfully'
        ]);
    }

    #[Route('/{id}/add-bidder', name: 'tender_add_bidder', methods: ['POST'])]
    public function addSuccessfulBidder(Request $request, int $id): JsonResponse
    {
        $tender = $this->tenderRepository->find($id);

        if (!$tender) {
            return $this->json([
                'success' => false,
                'message' => 'Tender not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $bidder = $data['bidder'] ?? null;

        if (!$bidder) {
            return $this->json([
                'success' => false,
                'message' => 'Bidder name is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $tender->addSuccessfulBidder($bidder);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'data' => $tender->toArray(),
            'message' => 'Successful bidder added successfully'
        ]);
    }

    #[Route('/check-number/{tenderNumber}', name: 'tender_check_number', methods: ['GET'])]
    public function checkTenderNumber(string $tenderNumber): JsonResponse
    {
        $exists = $this->tenderService->tenderNumberExists($tenderNumber);

        return $this->json([
            'success' => true,
            'data' => [
                'tenderNumber' => $tenderNumber,
                'exists' => $exists
            ],
            'message' => $exists ? 'Tender number already exists' : 'Tender number is available'
        ]);
    }

    #[Route('/import', name: 'tender_import', methods: ['POST'])]
    public function importFromExternalApi(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['apiUrl'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'API URL is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $apiUrl = $data['apiUrl'];
            
            // Validate URL format
            if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid URL format'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get pagination parameters with defaults
            $start = $data['start'] ?? 0;
            $length = $data['length'] ?? 10;
            $draw = $data['draw'] ?? 1;

            // Validate parameters
            if ($start < 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Start parameter must be non-negative'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($length < 1 || $length > 100) {
                return $this->json([
                    'success' => false,
                    'message' => 'Length parameter must be between 1 and 100'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($draw < 1) {
                return $this->json([
                    'success' => false,
                    'message' => 'Draw parameter must be positive'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Import data from external API
            $results = $this->tenderService->fetchAndSaveTenderData($apiUrl, $start, $length, $draw);

            return $this->json([
                'success' => true,
                'data' => $results,
                'message' => 'Tender data import completed'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error importing tender data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/import-batch', name: 'tender_import_batch', methods: ['POST'])]
    public function batchImportFromExternalApi(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['apiUrl'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'API URL is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $apiUrl = $data['apiUrl'];
            
            // Validate URL format
            if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid URL format'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get batch parameters with defaults
            $batchSize = $data['batchSize'] ?? 50;
            $maxRecords = $data['maxRecords'] ?? null;
            $startFrom = $data['startFrom'] ?? 0;

            // Validate parameters
            if ($batchSize < 1 || $batchSize > 100) {
                return $this->json([
                    'success' => false,
                    'message' => 'Batch size must be between 1 and 100'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($startFrom < 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Start from parameter must be non-negative'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($maxRecords !== null && $maxRecords < 1) {
                return $this->json([
                    'success' => false,
                    'message' => 'Max records parameter must be positive'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Import data from external API in batches
            $results = $this->tenderService->batchImportTenderData($apiUrl, $batchSize, $maxRecords, $startFrom);

            return $this->json([
                'success' => true,
                'data' => $results,
                'message' => 'Batch tender data import completed'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error importing tender data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 