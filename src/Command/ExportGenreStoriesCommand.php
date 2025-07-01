<?php

namespace App\Command;

use App\Entity\GenrePlot;
use App\Entity\GenreStory;
use App\Repository\GenrePlotRepository;
use App\Repository\GenreStoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-genre-stories',
    description: 'Export genre stories to a JSON file',
)]
class ExportGenreStoriesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GenrePlotRepository $genrePlotRepository,
        private GenreStoryRepository $genreStoryRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: genre_stories_export.json)', 'genre_stories_export.json')
            ->addOption('plot', 'p', InputOption::VALUE_OPTIONAL, 'Export stories for a specific plot ID only')
            ->addOption('genre', 'g', InputOption::VALUE_OPTIONAL, 'Export stories for all plots in a specific genre')
            ->addOption('age-group', 'a', InputOption::VALUE_OPTIONAL, 'Export stories for a specific age group only (7-10, 11-14, 15-18)')
            ->addOption('complete-only', 'c', InputOption::VALUE_NONE, 'Export only complete stories (all 5 chapters)')
            ->addOption('include-quiz', null, InputOption::VALUE_NONE, 'Include quiz questions in the export')
            ->addOption('include-images', 'i', InputOption::VALUE_NONE, 'Include image prompts in the export')
            ->addOption('with-images-only', 'w', InputOption::VALUE_NONE, 'Export only stories that have generated images')
            ->setHelp('This command exports genre stories to a JSON file. You can filter by plot, genre, or age group. Use --complete-only to export only stories with all 5 chapters. Use --include-quiz to include quiz questions and --include-images to include image prompts. Use --with-images-only to export only stories that have generated images.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputFile = $input->getOption('output');
        $specificPlotId = $input->getOption('plot');
        $specificGenre = $input->getOption('genre');
        $specificAgeGroup = $input->getOption('age-group');
        $completeOnly = $input->getOption('complete-only');
        $includeQuiz = $input->getOption('include-quiz');
        $includeImages = $input->getOption('include-images');
        $withImagesOnly = $input->getOption('with-images-only');

        $io->title('Exporting Genre Stories to JSON');

        // Validate age group if specified
        if ($specificAgeGroup && !in_array($specificAgeGroup, GenreStory::getAgeGroups())) {
            $io->error("Invalid age group: {$specificAgeGroup}. Valid options are: " . implode(', ', GenreStory::getAgeGroups()));
            return Command::FAILURE;
        }

        // Get plots to process
        if ($specificPlotId) {
            $plot = $this->genrePlotRepository->find($specificPlotId);
            if (!$plot) {
                $io->error("Plot with ID {$specificPlotId} not found.");
                return Command::FAILURE;
            }
            $plots = [$plot];
            $io->text("Exporting stories for specific plot: <info>{$plot->getTitle()}</info>");
        } elseif ($specificGenre) {
            $plots = $this->genrePlotRepository->findByGenreName($specificGenre);
            if (empty($plots)) {
                $io->error("No plots found for genre: {$specificGenre}");
                return Command::FAILURE;
            }
            $io->text("Exporting stories for all plots in genre: <info>{$specificGenre}</info> (<info>" . count($plots) . " plots</info>)");
        } else {
            $plots = $this->genrePlotRepository->findActivePlots();
            $io->text("Exporting stories for all active plots: <info>" . count($plots) . " plots</info>");
        }

        $ageGroups = $specificAgeGroup ? [$specificAgeGroup] : GenreStory::getAgeGroups();
        $exportData = ['books' => []];
        $totalStories = 0;
        $completeStories = 0;
        $incompleteStories = 0;
        $storiesWithImages = 0;
        $storiesWithoutImages = 0;

        foreach ($plots as $plot) {
            $io->text("Processing plot: {$plot->getTitle()} ({$plot->getGenre()->getGenreName()})");
            
            foreach ($ageGroups as $ageGroup) {
                $stories = $this->genreStoryRepository->findCompleteStoryByPlotAndAgeGroup($plot, $ageGroup);
                
                if (empty($stories)) {
                    $io->text("  ⏭️  No stories found for age group {$ageGroup}");
                    continue;
                }

                // Check if story is complete (5 chapters)
                $isComplete = count($stories) >= 5;
                
                if ($completeOnly && !$isComplete) {
                    $io->text("  ⏭️  Skipping incomplete story for age group {$ageGroup} ({$isComplete} chapters)");
                    $incompleteStories++;
                    continue;
                }

                // Sort stories by chapter number
                usort($stories, fn($a, $b) => $a->getChapterNumber() <=> $b->getChapterNumber());

                // Filter stories with images if requested
                if ($withImagesOnly) {
                    $originalCount = count($stories);
                    $stories = array_filter($stories, function($story) {
                        return $this->hasGeneratedImages($story);
                    });
                    $stories = array_values($stories); // Re-index array
                    
                    if (empty($stories)) {
                        $io->text("  ⏭️  No stories with generated images found for age group {$ageGroup}");
                        $storiesWithoutImages += $originalCount;
                        continue;
                    }
                    
                    $storiesWithImages += count($stories);
                    $storiesWithoutImages += ($originalCount - count($stories));
                    $io->text("  📸 Filtered to <info>" . count($stories) . " chapters with images</info> for age group {$ageGroup}");
                }

                // Create book entry for each chapter
                foreach ($stories as $story) {
                    $bookData = $this->formatStoryForExport($story, $includeQuiz, $includeImages);
                    if ($bookData['images'] !== null) {
                        $exportData['books'][] = $bookData;
                        $totalStories++;
                    }
                }

                if ($isComplete) {
                    $completeStories++;
                } else {
                    $incompleteStories++;
                }

                $io->text("  ✓ Exported <info>" . count($stories) . " chapters</info> for age group {$ageGroup}" . ($isComplete ? " (complete)" : " (incomplete)"));
            }
        }

        // Write to JSON file
        try {
            $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($jsonContent === false) {
                throw new \Exception('Failed to encode JSON: ' . json_last_error_msg());
            }

            file_put_contents($outputFile, $jsonContent);
            
            $successMessages = [
                "Genre stories export completed!",
                "Output file: <info>{$outputFile}</info>",
                "Total stories exported: <info>{$totalStories}</info>",
                "Complete stories: <info>{$completeStories}</info>",
                "Incomplete stories: <info>{$incompleteStories}</info>",
                "Plots processed: <info>" . count($plots) . "</info>",
                "Age groups processed: <info>" . implode(', ', $ageGroups) . "</info>",
                "Quiz included: " . ($includeQuiz ? "Yes" : "No"),
                "Images included: " . ($includeImages ? "Yes" : "No"),
                "Stories with images only: " . ($withImagesOnly ? "Yes" : "No")
            ];

            if ($withImagesOnly) {
                $successMessages[] = "Stories with images: <info>{$storiesWithImages}</info>";
                $successMessages[] = "Stories without images (skipped): <info>{$storiesWithoutImages}</info>";
            }

            $io->success($successMessages);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to write JSON file: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function formatStoryForExport(GenreStory $story, bool $includeQuiz, bool $includeImages): array
    {
        $plot = $story->getPlot();
        $genre = $plot->getGenre();
        
        // Generate book ID
        $genreSlug = strtolower(str_replace(' ', '_', $genre->getGenreName()));
        $plotId = $plot->getId();
        $ageGroupSlug = str_replace('-', '_', $story->getAgeGroup());
        $bookId = "{$genreSlug}_{$plotId}_{$ageGroupSlug}";

        // Determine reading level based on age group
        $readingLevel = $this->getReadingLevel($story->getAgeGroup());

        // Format quiz questions if included
        $quiz = null;
        if ($includeQuiz && $story->getQuiz()) {
            $quiz = $this->formatQuizForExport($story->getQuiz());
        }

        // Format images if included
        $images = null;
        $vocabulary = $story->getVocabulary() ?? [];
        
        if ($includeImages) {
            $imagePrompts = $vocabulary['image_prompts'] ?? [];
            $sharedImages = $vocabulary['shared_images'] ?? [];
            $oldImages = $vocabulary['images'] ?? [];
            
            $images = $this->formatImagesForExport($imagePrompts, $sharedImages, $oldImages, $bookId, $story->getChapterNumber());
        }

        return [
            'book_id' => $bookId,
            'title' => $plot->getTitle(),
            'genre' => $genre->getGenreName(),
            'sub_genre' => $plot->getSubcategory() ?? 'General',
            'chapter_number' => $story->getChapterNumber(),
            'chapter_name' => $this->generateChapterName($plot->getTitle(), $story->getChapterNumber()),
            'content' => $story->getContent(),
            'quiz' => $quiz,
            'images' => $images,
            'word_count' => $story->getWordCount() ?? 0,
            'reading_level' => $readingLevel
        ];
    }

    private function formatQuizForExport(?array $quizData): ?array
    {
        if (!$quizData || !isset($quizData['questions'])) {
            return null;
        }

        $formattedQuestions = [];
        foreach ($quizData['questions'] as $question) {
            $options = $question['options'] ?? [];
            $correctAnswerIndex = $question['correct_answer'] ?? 0;
            
            $formattedQuestions[] = [
                'question' => $question['question'] ?? '',
                'options' => $options,
                'correct_answer' => $options[$correctAnswerIndex] ?? ''
            ];
        }

        return [
            'questions' => $formattedQuestions
        ];
    }

    private function formatImagesForExport(array $imagePrompts, array $sharedImages, array $oldImages, string $bookId, int $chapterNumber): ?array
    {
        $illustrations = [];
        $baseDir = __DIR__ . '/../../public/assets/story-images/';
        
        // Add shared images if available (actual generated images)
        foreach ($sharedImages as $imageNumber => $imageData) {
            $filename = $imageData['filename'] ?? '';
            if ($filename && file_exists($baseDir . $filename)) {
                $illustrations[] = $filename;
            }
        }
        
        // Add old images if available (actual generated images)
        foreach ($oldImages as $imageNumber => $imageData) {
            $filename = $imageData['filename'] ?? '';
            if ($filename && file_exists($baseDir . $filename)) {
                $illustrations[] = $filename;
            }
        }
        
        // If no actual images, use image prompts to generate placeholder filenames (but only if file exists)
        if (empty($illustrations) && !empty($imagePrompts)) {
            foreach ($imagePrompts as $index => $prompt) {
                $filename = "{$bookId}_chapter{$chapterNumber}_img" . ($index + 1) . ".jpg";
                if (file_exists($baseDir . $filename)) {
                    $illustrations[] = $filename;
                }
            }
        }

        // Only include stories with at least 2 images on disk
        if (count($illustrations) < 2) {
            return null;
        }

        return [
            'chapter_cover' => $illustrations[0] ?? null,
            'illustrations' => $illustrations
        ];
    }

    private function getReadingLevel(string $ageGroup): string
    {
        return match ($ageGroup) {
            GenreStory::AGE_GROUP_7_10 => 'Explorer',
            GenreStory::AGE_GROUP_11_14 => 'Builder',
            GenreStory::AGE_GROUP_15_18 => 'Challenger',
            default => 'Builder'
        };
    }

    private function generateChapterName(string $plotTitle, int $chapterNumber): string
    {
        $baseName = "Chapter {$chapterNumber}";
        return "{$baseName}";
    }

    private function hasGeneratedImages(GenreStory $story): bool
    {
        $vocabulary = $story->getVocabulary() ?? [];
        
        // Check for shared images (new system)
        $hasSharedImages = isset($vocabulary['shared_images']) && 
                          is_array($vocabulary['shared_images']) && 
                          !empty($vocabulary['shared_images']);
        
        // Check for old-style images
        $hasOldImages = isset($vocabulary['images']) && 
                       is_array($vocabulary['images']) && 
                       !empty($vocabulary['images']);
        
        // Only return true if there are actual generated images, not just prompts
        return $hasSharedImages || $hasOldImages;
    }
} 