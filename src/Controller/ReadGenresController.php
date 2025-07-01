<?php

namespace App\Controller;

use App\Entity\ReadGenres;
use App\Repository\ReadGenresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/read-genres')]
class ReadGenresController extends AbstractController
{
    public function __construct(
        private ReadGenresRepository $readGenresRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'read_genres_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $genres = $this->readGenresRepository->findActiveGenres();
        
        return $this->json([
            'success' => true,
            'data' => $genres,
            'count' => count($genres)
        ]);
    }

    #[Route('/{id}', name: 'read_genres_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $genre = $this->readGenresRepository->find($id);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $genre
        ]);
    }

    #[Route('', name: 'read_genres_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['genreName']) || !isset($data['subcategories'])) {
            return $this->json([
                'success' => false,
                'message' => 'Genre name and subcategories are required'
            ], 400);
        }

        // Check if genre already exists
        $existingGenre = $this->readGenresRepository->findByGenreName($data['genreName']);
        if ($existingGenre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre already exists'
            ], 409);
        }

        $genre = new ReadGenres();
        $genre->setGenreName($data['genreName']);
        $genre->setSubcategories($data['subcategories']);

        $errors = $this->validator->validate($genre);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], 400);
        }

        $this->entityManager->persist($genre);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Genre created successfully',
            'data' => $genre
        ], 201);
    }

    #[Route('/{id}', name: 'read_genres_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $genre = $this->readGenresRepository->find($id);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['genreName'])) {
            $genre->setGenreName($data['genreName']);
        }

        if (isset($data['subcategories'])) {
            $genre->setSubcategories($data['subcategories']);
        }

        if (isset($data['isActive'])) {
            $genre->setIsActive($data['isActive']);
        }

        $genre->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($genre);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], 400);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Genre updated successfully',
            'data' => $genre
        ]);
    }

    #[Route('/{id}', name: 'read_genres_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $genre = $this->readGenresRepository->find($id);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $this->entityManager->remove($genre);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Genre deleted successfully'
        ]);
    }

    #[Route('/{id}/toggle', name: 'read_genres_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        $genre = $this->readGenresRepository->find($id);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $genre->setIsActive(!$genre->isActive());
        $genre->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Genre status toggled successfully',
            'data' => $genre
        ]);
    }

    #[Route('/search/subcategory', name: 'read_genres_search_subcategory', methods: ['GET'])]
    public function searchBySubcategory(Request $request): JsonResponse
    {
        $subcategory = $request->query->get('q');
        
        if (!$subcategory) {
            return $this->json([
                'success' => false,
                'message' => 'Subcategory query parameter is required'
            ], 400);
        }

        $genres = $this->readGenresRepository->findBySubcategory($subcategory);

        return $this->json([
            'success' => true,
            'data' => $genres,
            'count' => count($genres)
        ]);
    }
} 