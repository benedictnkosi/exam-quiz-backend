<?php

namespace App\Controller;

use App\Entity\GenrePlot;
use App\Entity\ReadGenres;
use App\Repository\GenrePlotRepository;
use App\Repository\ReadGenresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/genre-plots')]
class GenrePlotController extends AbstractController
{
    public function __construct(
        private GenrePlotRepository $genrePlotRepository,
        private ReadGenresRepository $readGenresRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'genre_plots_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $genreName = $request->query->get('genre');
        $limit = (int) $request->query->get('limit', 20);
        $offset = (int) $request->query->get('offset', 0);

        if ($genreName) {
            $plots = $this->genrePlotRepository->findByGenreName($genreName);
        } else {
            $plots = $this->genrePlotRepository->findActivePlots();
        }

        // Apply pagination
        $total = count($plots);
        $plots = array_slice($plots, $offset, $limit);

        return $this->json([
            'success' => true,
            'data' => $plots,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    }

    #[Route('/{id}', name: 'genre_plots_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($id);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $plot
        ]);
    }

    #[Route('/genre/{genreId}', name: 'genre_plots_by_genre', methods: ['GET'])]
    public function getByGenre(int $genreId): JsonResponse
    {
        $genre = $this->readGenresRepository->find($genreId);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $plots = $this->genrePlotRepository->findByGenre($genre);

        return $this->json([
            'success' => true,
            'data' => $plots,
            'count' => count($plots)
        ]);
    }

    #[Route('/genre/{genreId}/subcategory/{subcategory}', name: 'genre_plots_by_genre_subcategory', methods: ['GET'])]
    public function getByGenreAndSubcategory(int $genreId, string $subcategory): JsonResponse
    {
        $genre = $this->readGenresRepository->find($genreId);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $plots = $this->genrePlotRepository->findByGenreAndSubcategory($genre, $subcategory);

        return $this->json([
            'success' => true,
            'data' => $plots,
            'count' => count($plots),
            'genre' => $genre->getGenreName(),
            'subcategory' => $subcategory
        ]);
    }

    #[Route('/random/{genreId}', name: 'genre_plots_random', methods: ['GET'])]
    public function getRandomByGenre(int $genreId): JsonResponse
    {
        $genre = $this->readGenresRepository->find($genreId);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $plot = $this->genrePlotRepository->findRandomByGenre($genre);

        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'No plots found for this genre'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $plot
        ]);
    }

    #[Route('', name: 'genre_plots_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['genreId']) || !isset($data['title']) || !isset($data['synopsis'])) {
            return $this->json([
                'success' => false,
                'message' => 'Genre ID, title, and synopsis are required'
            ], 400);
        }

        $genre = $this->readGenresRepository->find($data['genreId']);
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $plot = new GenrePlot();
        $plot->setGenre($genre);
        $plot->setSubcategory($data['subcategory'] ?? null);
        $plot->setTitle($data['title']);
        $plot->setSynopsis($data['synopsis']);
        $plot->setConflict($data['conflict'] ?? '');
        $plot->setCharacters($data['characters'] ?? '');
        $plot->setSetting($data['setting'] ?? '');
        $plot->setHook($data['hook'] ?? '');

        $errors = $this->validator->validate($plot);
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

        $this->entityManager->persist($plot);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Plot created successfully',
            'data' => $plot
        ], 201);
    }

    #[Route('/{id}', name: 'genre_plots_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($id);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $plot->setTitle($data['title']);
        }

        if (isset($data['synopsis'])) {
            $plot->setSynopsis($data['synopsis']);
        }

        if (isset($data['conflict'])) {
            $plot->setConflict($data['conflict']);
        }

        if (isset($data['characters'])) {
            $plot->setCharacters($data['characters']);
        }

        if (isset($data['setting'])) {
            $plot->setSetting($data['setting']);
        }

        if (isset($data['hook'])) {
            $plot->setHook($data['hook']);
        }

        if (isset($data['subcategory'])) {
            $plot->setSubcategory($data['subcategory']);
        }

        if (isset($data['isActive'])) {
            $plot->setIsActive($data['isActive']);
        }

        $plot->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($plot);
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
            'message' => 'Plot updated successfully',
            'data' => $plot
        ]);
    }

    #[Route('/{id}', name: 'genre_plots_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($id);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        $this->entityManager->remove($plot);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Plot deleted successfully'
        ]);
    }

    #[Route('/{id}/toggle', name: 'genre_plots_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($id);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        $plot->setIsActive(!$plot->isActive());
        $plot->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Plot status toggled successfully',
            'data' => $plot
        ]);
    }

    #[Route('/stats', name: 'genre_plots_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $totalPlots = $this->genrePlotRepository->count([]);
        $activePlots = $this->genrePlotRepository->count(['isActive' => true]);

        // Get plot counts by genre
        $genreStats = [];
        $genres = $this->readGenresRepository->findActiveGenres();
        
        foreach ($genres as $genre) {
            $plotCount = $this->genrePlotRepository->countByGenre($genre);
            if ($plotCount > 0) {
                $subcategoryStats = [];
                $subcategories = $genre->getSubcategories() ?? [];
                
                foreach ($subcategories as $subcategory) {
                    $subcategoryPlotCount = $this->genrePlotRepository->countByGenreAndSubcategory($genre, $subcategory);
                    if ($subcategoryPlotCount > 0) {
                        $subcategoryStats[] = [
                            'subcategory' => $subcategory,
                            'plot_count' => $subcategoryPlotCount
                        ];
                    }
                }
                
                $genreStats[] = [
                    'genre' => $genre->getGenreName(),
                    'plot_count' => $plotCount,
                    'subcategories' => $subcategoryStats
                ];
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'total_plots' => $totalPlots,
                'active_plots' => $activePlots,
                'genres_with_plots' => count($genreStats),
                'genre_breakdown' => $genreStats
            ]
        ]);
    }

    #[Route('/subcategories/{genreId}', name: 'genre_subcategories', methods: ['GET'])]
    public function getSubcategories(int $genreId): JsonResponse
    {
        $genre = $this->readGenresRepository->find($genreId);
        
        if (!$genre) {
            return $this->json([
                'success' => false,
                'message' => 'Genre not found'
            ], 404);
        }

        $subcategories = $genre->getSubcategories() ?? [];
        $subcategoryStats = [];

        foreach ($subcategories as $subcategory) {
            $plotCount = $this->genrePlotRepository->countByGenreAndSubcategory($genre, $subcategory);
            $subcategoryStats[] = [
                'subcategory' => $subcategory,
                'plot_count' => $plotCount
            ];
        }

        return $this->json([
            'success' => true,
            'data' => [
                'genre' => $genre->getGenreName(),
                'subcategories' => $subcategoryStats
            ]
        ]);
    }
} 