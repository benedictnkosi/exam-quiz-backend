<?php

namespace App\Controller;

use App\Entity\GenrePlot;
use App\Entity\GenreStory;
use App\Repository\GenrePlotRepository;
use App\Repository\GenreStoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/genre-stories')]
class GenreStoryController extends AbstractController
{
    public function __construct(
        private GenreStoryRepository $genreStoryRepository,
        private GenrePlotRepository $genrePlotRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'genre_stories_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $ageGroup = $request->query->get('age_group');
        $genreName = $request->query->get('genre');
        $chapterNumber = $request->query->get('chapter');
        $limit = (int) $request->query->get('limit', 20);
        $offset = (int) $request->query->get('offset', 0);

        if ($ageGroup && $genreName) {
            $stories = $this->genreStoryRepository->findByGenreNameAndAgeGroup($genreName, $ageGroup);
        } elseif ($ageGroup) {
            $stories = $this->genreStoryRepository->findByAgeGroup($ageGroup);
        } elseif ($genreName) {
            // Get all stories for plots in the genre
            $plots = $this->genrePlotRepository->findByGenreName($genreName);
            $stories = [];
            foreach ($plots as $plot) {
                $plotStories = $this->genreStoryRepository->findByPlot($plot);
                $stories = array_merge($stories, $plotStories);
            }
        } else {
            $stories = $this->genreStoryRepository->findActiveStories();
        }

        // Filter by chapter number if specified
        if ($chapterNumber) {
            $stories = array_filter($stories, function($story) use ($chapterNumber) {
                return $story->getChapterNumber() == $chapterNumber;
            });
        }

        // Apply pagination
        $total = count($stories);
        $stories = array_slice($stories, $offset, $limit);

        return $this->json([
            'success' => true,
            'data' => $stories,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    }

    #[Route('/{id}', name: 'genre_stories_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $story = $this->genreStoryRepository->find($id);
        
        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $story
        ]);
    }

    #[Route('/plot/{plotId}', name: 'genre_stories_by_plot', methods: ['GET'])]
    public function getByPlot(int $plotId): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($plotId);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        $stories = $this->genreStoryRepository->findByPlot($plot);

        return $this->json([
            'success' => true,
            'data' => $stories,
            'count' => count($stories)
        ]);
    }

    #[Route('/plot/{plotId}/age-group/{ageGroup}', name: 'genre_stories_by_plot_and_age', methods: ['GET'])]
    public function getByPlotAndAgeGroup(int $plotId, string $ageGroup): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($plotId);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $stories = $this->genreStoryRepository->findByPlotAndAgeGroup($plot, $ageGroup);

        if (empty($stories)) {
            return $this->json([
                'success' => false,
                'message' => 'No stories found for this plot and age group'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $stories,
            'count' => count($stories),
            'complete_story' => count($stories) >= 5
        ]);
    }

    #[Route('/plot/{plotId}/age-group/{ageGroup}/chapter/{chapterNumber}', name: 'genre_stories_by_plot_age_and_chapter', methods: ['GET'])]
    public function getByPlotAgeGroupAndChapter(int $plotId, string $ageGroup, int $chapterNumber): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($plotId);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $story = $this->genreStoryRepository->findByPlotAgeGroupAndChapter($plot, $ageGroup, $chapterNumber);

        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Chapter not found'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $story
        ]);
    }

    #[Route('/plot/{plotId}/age-group/{ageGroup}/complete', name: 'genre_stories_complete_story', methods: ['GET'])]
    public function getCompleteStory(int $plotId, string $ageGroup): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($plotId);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $stories = $this->genreStoryRepository->findCompleteStoryByPlotAndAgeGroup($plot, $ageGroup);

        if (empty($stories)) {
            return $this->json([
                'success' => false,
                'message' => 'No complete story found for this plot and age group'
            ], 404);
        }

        $isComplete = count($stories) >= 5;
        $totalWords = array_sum(array_map(fn($s) => $s->getWordCount() ?? 0, $stories));
        $totalReadingTime = array_sum(array_map(fn($s) => $s->getReadingTime() ?? 0, $stories));

        return $this->json([
            'success' => true,
            'data' => [
                'chapters' => $stories,
                'plot' => $plot,
                'age_group' => $ageGroup,
                'total_chapters' => count($stories),
                'is_complete' => $isComplete,
                'total_words' => $totalWords,
                'total_reading_time' => $totalReadingTime,
                'final_summary' => end($stories)->getAccumulativeSummary()
            ]
        ]);
    }

    #[Route('/random/{genreName}/{ageGroup}', name: 'genre_stories_random', methods: ['GET'])]
    public function getRandomByGenreAndAgeGroup(string $genreName, string $ageGroup): JsonResponse
    {
        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $stories = $this->genreStoryRepository->findByGenreNameAndAgeGroup($genreName, $ageGroup);

        if (empty($stories)) {
            return $this->json([
                'success' => false,
                'message' => 'No stories found for this genre and age group'
            ], 404);
        }

        $randomStory = $stories[array_rand($stories)];

        return $this->json([
            'success' => true,
            'data' => $randomStory
        ]);
    }

    #[Route('/random/{genreName}/{ageGroup}/complete', name: 'genre_stories_random_complete', methods: ['GET'])]
    public function getRandomCompleteStory(string $genreName, string $ageGroup): JsonResponse
    {
        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $stories = $this->genreStoryRepository->findByGenreNameAndAgeGroup($genreName, $ageGroup);

        if (empty($stories)) {
            return $this->json([
                'success' => false,
                'message' => 'No stories found for this genre and age group'
            ], 404);
        }

        // Group stories by plot to find complete stories
        $storiesByPlot = [];
        foreach ($stories as $story) {
            $plotId = $story->getPlot()->getId();
            if (!isset($storiesByPlot[$plotId])) {
                $storiesByPlot[$plotId] = [];
            }
            $storiesByPlot[$plotId][] = $story;
        }

        // Find complete stories (5 chapters)
        $completeStories = [];
        foreach ($storiesByPlot as $plotId => $plotStories) {
            if (count($plotStories) >= 5) {
                // Sort by chapter number
                usort($plotStories, fn($a, $b) => $a->getChapterNumber() <=> $b->getChapterNumber());
                $completeStories[] = $plotStories;
            }
        }

        if (empty($completeStories)) {
            return $this->json([
                'success' => false,
                'message' => 'No complete stories found for this genre and age group'
            ], 404);
        }

        $randomCompleteStory = $completeStories[array_rand($completeStories)];
        $plot = $randomCompleteStory[0]->getPlot();
        $totalWords = array_sum(array_map(fn($s) => $s->getWordCount() ?? 0, $randomCompleteStory));
        $totalReadingTime = array_sum(array_map(fn($s) => $s->getReadingTime() ?? 0, $randomCompleteStory));

        return $this->json([
            'success' => true,
            'data' => [
                'chapters' => $randomCompleteStory,
                'plot' => $plot,
                'age_group' => $ageGroup,
                'total_chapters' => count($randomCompleteStory),
                'total_words' => $totalWords,
                'total_reading_time' => $totalReadingTime,
                'final_summary' => end($randomCompleteStory)->getAccumulativeSummary()
            ]
        ]);
    }

    #[Route('', name: 'genre_stories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['plotId']) || !isset($data['ageGroup']) || !isset($data['content']) || !isset($data['chapterNumber'])) {
            return $this->json([
                'success' => false,
                'message' => 'Plot ID, age group, content, and chapter number are required'
            ], 400);
        }

        if (!in_array($data['ageGroup'], GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $plot = $this->genrePlotRepository->find($data['plotId']);
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        // Check if chapter already exists for this plot, age group, and chapter number
        $existingStory = $this->genreStoryRepository->findByPlotAgeGroupAndChapter($plot, $data['ageGroup'], $data['chapterNumber']);
        if ($existingStory) {
            return $this->json([
                'success' => false,
                'message' => 'Chapter already exists for this plot, age group, and chapter number'
            ], 409);
        }

        $story = new GenreStory();
        $story->setPlot($plot);
        $story->setAgeGroup($data['ageGroup']);
        $story->setChapterNumber($data['chapterNumber']);
        $story->setContent($data['content']);
        $story->setSummary($data['summary'] ?? '');
        $story->setAccumulativeSummary($data['accumulativeSummary'] ?? '');
        $story->setWordCount($data['wordCount'] ?? str_word_count($data['content']));
        $story->setReadingTime($data['readingTime'] ?? ceil(str_word_count($data['content']) / 200));
        $story->setVocabulary($data['vocabulary'] ?? []);

        $errors = $this->validator->validate($story);
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

        $this->entityManager->persist($story);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Story chapter created successfully',
            'data' => $story
        ], 201);
    }

    #[Route('/{id}', name: 'genre_stories_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $story = $this->genreStoryRepository->find($id);
        
        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['content'])) {
            $story->setContent($data['content']);
        }

        if (isset($data['summary'])) {
            $story->setSummary($data['summary']);
        }

        if (isset($data['accumulativeSummary'])) {
            $story->setAccumulativeSummary($data['accumulativeSummary']);
        }

        if (isset($data['wordCount'])) {
            $story->setWordCount($data['wordCount']);
        }

        if (isset($data['readingTime'])) {
            $story->setReadingTime($data['readingTime']);
        }

        if (isset($data['vocabulary'])) {
            $story->setVocabulary($data['vocabulary']);
        }

        if (isset($data['isActive'])) {
            $story->setIsActive($data['isActive']);
        }

        $story->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($story);
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
            'message' => 'Story updated successfully',
            'data' => $story
        ]);
    }

    #[Route('/{id}', name: 'genre_stories_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $story = $this->genreStoryRepository->find($id);
        
        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }

        $this->entityManager->remove($story);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Story deleted successfully'
        ]);
    }

    #[Route('/{id}/toggle', name: 'genre_stories_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        $story = $this->genreStoryRepository->find($id);
        
        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }

        $story->setIsActive(!$story->isActive());
        $story->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Story status toggled successfully',
            'data' => $story
        ]);
    }

    #[Route('/stats', name: 'genre_stories_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $totalStories = $this->genreStoryRepository->count([]);
        $activeStories = $this->genreStoryRepository->count(['isActive' => true]);

        // Get plot counts by genre
        $genreStats = [];
        $genres = $this->genrePlotRepository->findActivePlots();
        
        foreach ($genres as $plot) {
            $genreName = $plot->getGenre()->getGenreName();
            if (!isset($genreStats[$genreName])) {
                $genreStats[$genreName] = [
                    'total_chapters' => 0,
                    'complete_stories' => 0,
                    'age_groups' => []
                ];
            }
            
            $genreStats[$genreName]['total_chapters'] += $this->genreStoryRepository->countByPlot($plot);
            
            foreach (GenreStory::getAgeGroups() as $ageGroup) {
                if (!isset($genreStats[$genreName]['age_groups'][$ageGroup])) {
                    $genreStats[$genreName]['age_groups'][$ageGroup] = 0;
                }
                
                $chapterCount = $this->genreStoryRepository->countChaptersByPlotAndAgeGroup($plot, $ageGroup);
                $genreStats[$genreName]['age_groups'][$ageGroup] += $chapterCount;
                
                if ($chapterCount >= 5) {
                    $genreStats[$genreName]['complete_stories']++;
                }
            }
        }

        $ageGroupStats = $this->genreStoryRepository->getStatsByAgeGroup();

        return $this->json([
            'success' => true,
            'data' => [
                'total_chapters' => $totalStories,
                'active_chapters' => $activeStories,
                'age_group_breakdown' => $ageGroupStats,
                'genre_breakdown' => $genreStats
            ]
        ]);
    }

    #[Route('/{id}/images', name: 'genre_stories_images', methods: ['GET'])]
    public function getStoryImages(int $id): JsonResponse
    {
        $story = $this->genreStoryRepository->find($id);
        
        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }

        $vocabulary = $story->getVocabulary() ?? [];
        $imagePrompts = $vocabulary['image_prompts'] ?? [];
        $images = $vocabulary['images'] ?? [];

        // Construct image URLs from filenames
        $imageUrls = [];
        foreach ($images as $imageNumber => $imageData) {
            $filename = $imageData['filename'] ?? '';
            
            if ($filename) {
                $imageUrls[$imageNumber] = [
                    'filename' => $filename,
                    'url' => "/assets/story-images/{$filename}",
                    'prompt' => $imageData['prompt'] ?? '',
                    'size' => $imageData['size'] ?? '',
                    'format' => $imageData['format'] ?? '',
                    'generatedAt' => $imageData['generatedAt'] ?? ''
                ];
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'story' => [
                    'id' => $story->getId(),
                    'chapter_number' => $story->getChapterNumber(),
                    'age_group' => $story->getAgeGroup(),
                    'plot_title' => $story->getPlot()->getTitle(),
                    'genre' => $story->getPlot()->getGenre()->getGenreName()
                ],
                'image_prompts' => $imagePrompts,
                'generated_images' => $imageUrls,
                'has_images' => !empty($imageUrls),
                'image_count' => count($imageUrls)
            ]
        ]);
    }

    #[Route('/{id}/image-prompts', name: 'genre_stories_image_prompts', methods: ['GET'])]
    public function getImagePrompts(int $id): JsonResponse
    {
        $story = $this->genreStoryRepository->find($id);
        
        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Story not found'
            ], 404);
        }

        $vocabulary = $story->getVocabulary() ?? [];
        $imagePrompts = $vocabulary['image_prompts'] ?? [];

        if (empty($imagePrompts)) {
            return $this->json([
                'success' => false,
                'message' => 'No image prompts found for this story. Generate stories with --generate-images first.'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'story' => [
                    'id' => $story->getId(),
                    'chapter_number' => $story->getChapterNumber(),
                    'age_group' => $story->getAgeGroup(),
                    'plot_title' => $story->getPlot()->getTitle(),
                    'genre' => $story->getPlot()->getGenre()->getGenreName()
                ],
                'image_prompts' => $imagePrompts,
                'prompt_count' => count($imagePrompts)
            ]
        ]);
    }

    #[Route('/plot/{plotId}/age-group/{ageGroup}/chapter/{chapterNumber}/images', name: 'genre_stories_plot_age_chapter_images', methods: ['GET'])]
    public function getPlotAgeChapterImages(int $plotId, string $ageGroup, int $chapterNumber): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($plotId);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $story = $this->genreStoryRepository->findByPlotAgeGroupAndChapter($plot, $ageGroup, $chapterNumber);

        if (!$story) {
            return $this->json([
                'success' => false,
                'message' => 'Chapter not found'
            ], 404);
        }

        $vocabulary = $story->getVocabulary() ?? [];
        $imagePrompts = $vocabulary['image_prompts'] ?? [];
        $images = $vocabulary['images'] ?? [];

        // Construct image URLs from filenames
        $imageUrls = [];
        foreach ($images as $imageNumber => $imageData) {
            $filename = $imageData['filename'] ?? '';
            
            if ($filename) {
                $imageUrls[$imageNumber] = [
                    'filename' => $filename,
                    'url' => "/assets/story-images/{$filename}",
                    'prompt' => $imageData['prompt'] ?? '',
                    'size' => $imageData['size'] ?? '',
                    'format' => $imageData['format'] ?? '',
                    'generatedAt' => $imageData['generatedAt'] ?? ''
                ];
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'story' => $story,
                'image_prompts' => $imagePrompts,
                'generated_images' => $imageUrls,
                'has_images' => !empty($imageUrls),
                'image_count' => count($imageUrls)
            ]
        ]);
    }

    #[Route('/plot/{plotId}/age-group/{ageGroup}/complete/images', name: 'genre_stories_complete_story_images', methods: ['GET'])]
    public function getCompleteStoryImages(int $plotId, string $ageGroup): JsonResponse
    {
        $plot = $this->genrePlotRepository->find($plotId);
        
        if (!$plot) {
            return $this->json([
                'success' => false,
                'message' => 'Plot not found'
            ], 404);
        }

        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $stories = $this->genreStoryRepository->findCompleteStoryByPlotAndAgeGroup($plot, $ageGroup);

        if (empty($stories)) {
            return $this->json([
                'success' => false,
                'message' => 'No complete story found for this plot and age group'
            ], 404);
        }

        $allImages = [];
        $allPrompts = [];
        $totalImages = 0;

        foreach ($stories as $story) {
            $vocabulary = $story->getVocabulary() ?? [];
            $imagePrompts = $vocabulary['image_prompts'] ?? [];
            $images = $vocabulary['images'] ?? [];

            // Construct image URLs from filenames
            $imageUrls = [];
            foreach ($images as $imageNumber => $imageData) {
                $filename = $imageData['filename'] ?? '';
                
                if ($filename) {
                    $imageUrls[$imageNumber] = [
                        'filename' => $filename,
                        'url' => "/assets/story-images/{$filename}",
                        'prompt' => $imageData['prompt'] ?? '',
                        'size' => $imageData['size'] ?? '',
                        'format' => $imageData['format'] ?? '',
                        'generatedAt' => $imageData['generatedAt'] ?? ''
                    ];
                }
            }

            $allImages[$story->getChapterNumber()] = $imageUrls;
            $allPrompts[$story->getChapterNumber()] = $imagePrompts;
            $totalImages += count($imageUrls);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'plot' => $plot,
                'age_group' => $ageGroup,
                'total_chapters' => count($stories),
                'chapters_with_images' => array_keys(array_filter($allImages, fn($images) => !empty($images))),
                'chapters_with_prompts' => array_keys(array_filter($allPrompts, fn($prompts) => !empty($prompts))),
                'total_images' => $totalImages,
                'images_by_chapter' => $allImages,
                'prompts_by_chapter' => $allPrompts,
                'complete_story' => count($stories) >= 5
            ]
        ]);
    }

    #[Route('/random/{genreName}/{ageGroup}/complete/images', name: 'genre_stories_random_complete_images', methods: ['GET'])]
    public function getRandomCompleteStoryImages(string $genreName, string $ageGroup): JsonResponse
    {
        if (!in_array($ageGroup, GenreStory::getAgeGroups())) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid age group. Valid options are: ' . implode(', ', GenreStory::getAgeGroups())
            ], 400);
        }

        $stories = $this->genreStoryRepository->findByGenreNameAndAgeGroup($genreName, $ageGroup);

        if (empty($stories)) {
            return $this->json([
                'success' => false,
                'message' => 'No stories found for this genre and age group'
            ], 404);
        }

        // Group stories by plot to find complete stories
        $storiesByPlot = [];
        foreach ($stories as $story) {
            $plotId = $story->getPlot()->getId();
            if (!isset($storiesByPlot[$plotId])) {
                $storiesByPlot[$plotId] = [];
            }
            $storiesByPlot[$plotId][] = $story;
        }

        // Find complete stories (5 chapters)
        $completeStories = [];
        foreach ($storiesByPlot as $plotId => $plotStories) {
            if (count($plotStories) >= 5) {
                // Sort by chapter number
                usort($plotStories, fn($a, $b) => $a->getChapterNumber() <=> $b->getChapterNumber());
                $completeStories[] = $plotStories;
            }
        }

        if (empty($completeStories)) {
            return $this->json([
                'success' => false,
                'message' => 'No complete stories found for this genre and age group'
            ], 404);
        }

        $randomCompleteStory = $completeStories[array_rand($completeStories)];
        $plot = $randomCompleteStory[0]->getPlot();
        
        $allImages = [];
        $allPrompts = [];
        $totalImages = 0;

        foreach ($randomCompleteStory as $story) {
            $vocabulary = $story->getVocabulary() ?? [];
            $imagePrompts = $vocabulary['image_prompts'] ?? [];
            $images = $vocabulary['images'] ?? [];

            // Construct image URLs from filenames
            $imageUrls = [];
            foreach ($images as $imageNumber => $imageData) {
                $filename = $imageData['filename'] ?? '';
                
                if ($filename) {
                    $imageUrls[$imageNumber] = [
                        'filename' => $filename,
                        'url' => "/assets/story-images/{$filename}",
                        'prompt' => $imageData['prompt'] ?? '',
                        'size' => $imageData['size'] ?? '',
                        'format' => $imageData['format'] ?? '',
                        'generatedAt' => $imageData['generatedAt'] ?? ''
                    ];
                }
            }

            $allImages[$story->getChapterNumber()] = $imageUrls;
            $allPrompts[$story->getChapterNumber()] = $imagePrompts;
            $totalImages += count($imageUrls);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'chapters' => $randomCompleteStory,
                'plot' => $plot,
                'age_group' => $ageGroup,
                'total_chapters' => count($randomCompleteStory),
                'total_images' => $totalImages,
                'images_by_chapter' => $allImages,
                'prompts_by_chapter' => $allPrompts,
                'chapters_with_images' => array_keys(array_filter($allImages, fn($images) => !empty($images))),
                'chapters_with_prompts' => array_keys(array_filter($allPrompts, fn($prompts) => !empty($prompts)))
            ]
        ]);
    }

    #[Route('/stats/images', name: 'genre_stories_image_stats', methods: ['GET'])]
    public function getImageStats(): JsonResponse
    {
        $stories = $this->genreStoryRepository->findActiveStories();
        
        $totalStories = count($stories);
        $storiesWithPrompts = 0;
        $storiesWithImages = 0;
        $totalImages = 0;
        $totalPrompts = 0;

        $ageGroupStats = [];
        $genreStats = [];

        foreach ($stories as $story) {
            $vocabulary = $story->getVocabulary() ?? [];
            $imagePrompts = $vocabulary['image_prompts'] ?? [];
            $images = $vocabulary['images'] ?? [];

            if (!empty($imagePrompts)) {
                $storiesWithPrompts++;
                $totalPrompts += count($imagePrompts);
            }

            if (!empty($images)) {
                $storiesWithImages++;
                $totalImages += count($images);
            }

            // Age group stats
            $ageGroup = $story->getAgeGroup();
            if (!isset($ageGroupStats[$ageGroup])) {
                $ageGroupStats[$ageGroup] = [
                    'total_stories' => 0,
                    'stories_with_prompts' => 0,
                    'stories_with_images' => 0,
                    'total_prompts' => 0,
                    'total_images' => 0
                ];
            }
            
            $ageGroupStats[$ageGroup]['total_stories']++;
            if (!empty($imagePrompts)) {
                $ageGroupStats[$ageGroup]['stories_with_prompts']++;
                $ageGroupStats[$ageGroup]['total_prompts'] += count($imagePrompts);
            }
            if (!empty($images)) {
                $ageGroupStats[$ageGroup]['stories_with_images']++;
                $ageGroupStats[$ageGroup]['total_images'] += count($images);
            }

            // Genre stats
            $genreName = $story->getPlot()->getGenre()->getGenreName();
            if (!isset($genreStats[$genreName])) {
                $genreStats[$genreName] = [
                    'total_stories' => 0,
                    'stories_with_prompts' => 0,
                    'stories_with_images' => 0,
                    'total_prompts' => 0,
                    'total_images' => 0
                ];
            }
            
            $genreStats[$genreName]['total_stories']++;
            if (!empty($imagePrompts)) {
                $genreStats[$genreName]['stories_with_prompts']++;
                $genreStats[$genreName]['total_prompts'] += count($imagePrompts);
            }
            if (!empty($images)) {
                $genreStats[$genreName]['stories_with_images']++;
                $genreStats[$genreName]['total_images'] += count($images);
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'overall' => [
                    'total_stories' => $totalStories,
                    'stories_with_prompts' => $storiesWithPrompts,
                    'stories_with_images' => $storiesWithImages,
                    'total_prompts' => $totalPrompts,
                    'total_images' => $totalImages,
                    'prompt_coverage' => $totalStories > 0 ? round(($storiesWithPrompts / $totalStories) * 100, 2) : 0,
                    'image_coverage' => $totalStories > 0 ? round(($storiesWithImages / $totalStories) * 100, 2) : 0
                ],
                'age_group_breakdown' => $ageGroupStats,
                'genre_breakdown' => $genreStats
            ]
        ]);
    }
} 