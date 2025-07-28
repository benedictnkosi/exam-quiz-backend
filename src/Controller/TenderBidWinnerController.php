<?php

namespace App\Controller;

use App\Entity\TenderBidWinner;
use App\Repository\TenderBidWinnerRepository;
use App\Repository\TenderRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tender-bid-winners')]
class TenderBidWinnerController extends AbstractController
{
    public function __construct(
        private TenderBidWinnerRepository $tenderBidWinnerRepository,
        private TenderRepository $tenderRepository,
        private CompanyRepository $companyRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'tender_bid_winner_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $tenderId = $request->query->get('tenderId');
        $companyId = $request->query->get('companyId');

        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('tbw')
           ->from(TenderBidWinner::class, 'tbw')
           ->setFirstResult($offset)
           ->setMaxResults($limit)
           ->orderBy('tbw.created', 'DESC');

        if ($tenderId) {
            $qb->andWhere('tbw.tender = :tenderId')
               ->setParameter('tenderId', $tenderId);
        }

        if ($companyId) {
            $qb->andWhere('tbw.company = :companyId')
               ->setParameter('companyId', $companyId);
        }

        $query = $qb->getQuery();
        $bidWinners = $query->getResult();

        $total = $this->tenderBidWinnerRepository->count([]);

        return $this->json([
            'success' => true,
            'data' => [
                'bidWinners' => array_map(fn($winner) => $winner->toArray(), $bidWinners),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ],
            'message' => 'Tender bid winners retrieved successfully'
        ]);
    }

    #[Route('/{id}', name: 'tender_bid_winner_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $bidWinner = $this->tenderBidWinnerRepository->find($id);

        if (!$bidWinner) {
            return $this->json([
                'success' => false,
                'message' => 'Tender bid winner not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $bidWinner->toArray(),
            'message' => 'Tender bid winner retrieved successfully'
        ]);
    }

    #[Route('', name: 'tender_bid_winner_create', methods: ['POST'])]
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

            // Validate required fields
            $requiredFields = ['tenderId', 'companyId', 'companyName'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Check if tender exists
            $tender = $this->tenderRepository->find($data['tenderId']);
            if (!$tender) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tender not found'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if company exists
            $company = $this->companyRepository->find($data['companyId']);
            if (!$company) {
                return $this->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if bid winner already exists for this tender and company
            $existingBidWinner = $this->tenderBidWinnerRepository->findByTenderAndCompany(
                $data['tenderId'],
                $data['companyId']
            );

            if ($existingBidWinner) {
                return $this->json([
                    'success' => false,
                    'message' => 'Bid winner already exists for this tender and company'
                ], Response::HTTP_BAD_REQUEST);
            }

            $bidWinner = new TenderBidWinner();
            $bidWinner->setTender($tender);
            $bidWinner->setCompany($company);
            $bidWinner->setCompanyName($data['companyName']);

            $this->entityManager->persist($bidWinner);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'data' => $bidWinner->toArray(),
                'message' => 'Tender bid winner created successfully'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error creating tender bid winner: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'tender_bid_winner_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $bidWinner = $this->tenderBidWinnerRepository->find($id);

        if (!$bidWinner) {
            return $this->json([
                'success' => false,
                'message' => 'Tender bid winner not found'
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

            if (isset($data['companyName'])) {
                $bidWinner->setCompanyName($data['companyName']);
            }

            $bidWinner->setUpdated(new \DateTime());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'data' => $bidWinner->toArray(),
                'message' => 'Tender bid winner updated successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error updating tender bid winner: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'tender_bid_winner_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $bidWinner = $this->tenderBidWinnerRepository->find($id);

        if (!$bidWinner) {
            return $this->json([
                'success' => false,
                'message' => 'Tender bid winner not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($bidWinner);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Tender bid winner deleted successfully'
        ]);
    }

    #[Route('/tender/{tenderId}', name: 'tender_bid_winners_by_tender', methods: ['GET'])]
    public function getByTender(int $tenderId): JsonResponse
    {
        $bidWinners = $this->tenderBidWinnerRepository->findByTenderId($tenderId);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($winner) => $winner->toArray(), $bidWinners),
            'message' => 'Tender bid winners retrieved successfully'
        ]);
    }

    #[Route('/company/{companyId}', name: 'tender_bid_winners_by_company', methods: ['GET'])]
    public function getByCompany(int $companyId): JsonResponse
    {
        $bidWinners = $this->tenderBidWinnerRepository->findByCompanyId($companyId);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($winner) => $winner->toArray(), $bidWinners),
            'message' => 'Company bid winners retrieved successfully'
        ]);
    }

    #[Route('/winning-companies', name: 'winning_companies', methods: ['GET'])]
    public function getWinningCompanies(): JsonResponse
    {
        $winningCompanies = $this->tenderBidWinnerRepository->findWinningCompanies();

        return $this->json([
            'success' => true,
            'data' => $winningCompanies,
            'message' => 'Winning companies retrieved successfully'
        ]);
    }

    #[Route('/recent', name: 'recent_bid_winners', methods: ['GET'])]
    public function getRecentBidWinners(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);
        $bidWinners = $this->tenderBidWinnerRepository->findRecentBidWinners($limit);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($winner) => $winner->toArray(), $bidWinners),
            'message' => 'Recent bid winners retrieved successfully'
        ]);
    }
} 