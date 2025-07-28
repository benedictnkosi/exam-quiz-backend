<?php

namespace App\Controller;

use App\Entity\CompanyDirector;
use App\Repository\CompanyDirectorRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/company-directors')]
class CompanyDirectorController extends AbstractController
{
    public function __construct(
        private CompanyDirectorRepository $companyDirectorRepository,
        private CompanyRepository $companyRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'company_director_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $companyId = $request->query->get('companyId');
        $directorName = $request->query->get('directorName');

        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('cd')
           ->from(CompanyDirector::class, 'cd')
           ->setFirstResult($offset)
           ->setMaxResults($limit)
           ->orderBy('cd.directorName', 'ASC');

        if ($companyId) {
            $qb->andWhere('cd.company = :companyId')
               ->setParameter('companyId', $companyId);
        }

        if ($directorName) {
            $qb->andWhere('cd.directorName LIKE :directorName')
               ->setParameter('directorName', '%' . $directorName . '%');
        }

        $query = $qb->getQuery();
        $directors = $query->getResult();

        $total = $this->companyDirectorRepository->count([]);

        return $this->json([
            'success' => true,
            'data' => [
                'directors' => array_map(fn($director) => $director->toArray(), $directors),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ],
            'message' => 'Company directors retrieved successfully'
        ]);
    }

    #[Route('/{id}', name: 'company_director_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $director = $this->companyDirectorRepository->find($id);

        if (!$director) {
            return $this->json([
                'success' => false,
                'message' => 'Company director not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $director->toArray(),
            'message' => 'Company director retrieved successfully'
        ]);
    }

    #[Route('', name: 'company_director_create', methods: ['POST'])]
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
            $requiredFields = ['directorName', 'directorIdNumber', 'companyId'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Check if company exists
            $company = $this->companyRepository->find($data['companyId']);
            if (!$company) {
                return $this->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if director already exists for this company and ID number
            $existingDirector = $this->companyDirectorRepository->findByCompanyAndIdNumber(
                $data['companyId'],
                $data['directorIdNumber']
            );

            if ($existingDirector) {
                return $this->json([
                    'success' => false,
                    'message' => 'Director already exists for this company with this ID number'
                ], Response::HTTP_BAD_REQUEST);
            }

            $director = new CompanyDirector();
            $director->setDirectorName($data['directorName']);
            $director->setDirectorIdNumber($data['directorIdNumber']);
            $director->setCompany($company);

            $this->entityManager->persist($director);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'data' => $director->toArray(),
                'message' => 'Company director created successfully'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error creating company director: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'company_director_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $director = $this->companyDirectorRepository->find($id);

        if (!$director) {
            return $this->json([
                'success' => false,
                'message' => 'Company director not found'
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

            if (isset($data['directorName'])) {
                $director->setDirectorName($data['directorName']);
            }

            if (isset($data['directorIdNumber'])) {
                $director->setDirectorIdNumber($data['directorIdNumber']);
            }

            $director->setUpdated(new \DateTime());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'data' => $director->toArray(),
                'message' => 'Company director updated successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error updating company director: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'company_director_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $director = $this->companyDirectorRepository->find($id);

        if (!$director) {
            return $this->json([
                'success' => false,
                'message' => 'Company director not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($director);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Company director deleted successfully'
        ]);
    }

    #[Route('/company/{companyId}', name: 'company_directors_by_company', methods: ['GET'])]
    public function getByCompany(int $companyId): JsonResponse
    {
        $directors = $this->companyDirectorRepository->findByCompanyId($companyId);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($director) => $director->toArray(), $directors),
            'message' => 'Company directors retrieved successfully'
        ]);
    }

    #[Route('/search/name/{directorName}', name: 'company_directors_by_name', methods: ['GET'])]
    public function getByName(string $directorName): JsonResponse
    {
        $directors = $this->companyDirectorRepository->findByDirectorName($directorName);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($director) => $director->toArray(), $directors),
            'message' => 'Company directors retrieved successfully'
        ]);
    }

    #[Route('/search/id-number/{idNumber}', name: 'company_director_by_id_number', methods: ['GET'])]
    public function getByIdNumber(string $idNumber): JsonResponse
    {
        $director = $this->companyDirectorRepository->findByIdNumber($idNumber);

        if (!$director) {
            return $this->json([
                'success' => false,
                'message' => 'Company director not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $director->toArray(),
            'message' => 'Company director retrieved successfully'
        ]);
    }

    #[Route('/unique-names', name: 'unique_director_names', methods: ['GET'])]
    public function getUniqueDirectorNames(): JsonResponse
    {
        $uniqueNames = $this->companyDirectorRepository->findUniqueDirectorNames();

        return $this->json([
            'success' => true,
            'data' => $uniqueNames,
            'message' => 'Unique director names retrieved successfully'
        ]);
    }

    #[Route('/multiple-companies', name: 'directors_multiple_companies', methods: ['GET'])]
    public function getDirectorsOfMultipleCompanies(): JsonResponse
    {
        $directors = $this->companyDirectorRepository->findDirectorsOfMultipleCompanies();

        return $this->json([
            'success' => true,
            'data' => $directors,
            'message' => 'Directors of multiple companies retrieved successfully'
        ]);
    }

    #[Route('/recent', name: 'recent_directors', methods: ['GET'])]
    public function getRecentDirectors(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);
        $directors = $this->companyDirectorRepository->findRecentDirectors($limit);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($director) => $director->toArray(), $directors),
            'message' => 'Recent directors retrieved successfully'
        ]);
    }
} 