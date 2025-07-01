<?php

namespace App\Command;

use App\Entity\GenreStory;
use App\Repository\GenreStoryRepository;
use App\Service\OpenAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-story-images',
    description: 'Generate images for story chapters using AI image generation (shared across all age groups)',
)]
class GenerateStoryImagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GenreStoryRepository $genreStoryRepository,
        private OpenAIService $openAIService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('story-id', 's', InputOption::VALUE_OPTIONAL, 'Generate images for a specific story ID only')
            ->addOption('plot-id', 'p', InputOption::VALUE_OPTIONAL, 'Generate images for all stories in a specific plot')
            ->addOption('age-group', 'a', InputOption::VALUE_OPTIONAL, 'Generate images for stories in a specific age group only (7-10, 11-14, 15-18)')
            ->addOption('chapter', 'c', InputOption::VALUE_OPTIONAL, 'Generate images for a specific chapter number only')
            ->addOption('replace', 'r', InputOption::VALUE_NONE, 'Replace existing images')
            ->addOption('save-to-disk', 'd', InputOption::VALUE_NONE, 'Save generated images to disk')
            ->addOption('image-size', 'i', InputOption::VALUE_OPTIONAL, 'Image size (512x512, 1024x1024, 1792x1024, 1024x1792)', '1024x1024')
            ->setHelp('This command generates images for story chapters using AI image generation. Images are shared across all age groups for the same plot/chapter combination. Use --story-id to generate for a specific story, --plot-id for all stories in a plot, or --age-group for a specific age group. By default, existing images are skipped. Use --replace to regenerate existing images. Use --save-to-disk to save images to the filesystem.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specificStoryId = $input->getOption('story-id');
        $specificPlotId = $input->getOption('plot-id');
        $specificAgeGroup = $input->getOption('age-group');
        $specificChapter = $input->getOption('chapter');
        $replaceExisting = $input->getOption('replace');
        $saveToDisk = $input->getOption('save-to-disk');
        $imageSize = $input->getOption('image-size');

        $io->title('Generating Story Images with AI (Shared Across Age Groups)');

        // Validate age group if specified
        if ($specificAgeGroup && !in_array($specificAgeGroup, GenreStory::getAgeGroups())) {
            $io->error("Invalid age group: {$specificAgeGroup}. Valid options are: " . implode(', ', GenreStory::getAgeGroups()));
            return Command::FAILURE;
        }

        // Validate image size
        $validSizes = ['512x512', '1024x1024', '1792x1024', '1024x1792'];
        if (!in_array($imageSize, $validSizes)) {
            $io->error("Invalid image size: {$imageSize}. Valid options are: " . implode(', ', $validSizes));
            return Command::FAILURE;
        }

        // Get stories to process
        if ($specificStoryId) {
            $story = $this->genreStoryRepository->find($specificStoryId);
            if (!$story) {
                $io->error("Story with ID {$specificStoryId} not found.");
                return Command::FAILURE;
            }
            $stories = [$story];
            $io->text("Generating images for specific story: <info>Chapter {$story->getChapterNumber()} - {$story->getAgeGroup()}</info>");
        } elseif ($specificPlotId) {
            $plot = $this->entityManager->getRepository(\App\Entity\GenrePlot::class)->find($specificPlotId);
            if (!$plot) {
                $io->error("Plot with ID {$specificPlotId} not found.");
                return Command::FAILURE;
            }
            $stories = $this->genreStoryRepository->findByPlot($plot);
            $io->text("Generating images for all stories in plot: <info>{$plot->getTitle()}</info> (<info>" . count($stories) . " chapters</info>)");
        } else {
            $stories = $this->genreStoryRepository->findActiveStories();
            $io->text("Generating images for all active stories: <info>" . count($stories) . " chapters</info>");
        }

        // Filter by age group if specified
        if ($specificAgeGroup) {
            $stories = array_filter($stories, function($story) use ($specificAgeGroup) {
                return $story->getAgeGroup() === $specificAgeGroup;
            });
            $io->text("Filtered to age group: <info>{$specificAgeGroup}</info> (<info>" . count($stories) . " chapters</info>)");
        }

        // Filter by chapter if specified
        if ($specificChapter) {
            $stories = array_filter($stories, function($story) use ($specificChapter) {
                return $story->getChapterNumber() == $specificChapter;
            });
            $io->text("Filtered to chapter: <info>{$specificChapter}</info> (<info>" . count($stories) . " chapters</info>)");
        }

        if (empty($stories)) {
            $io->warning("No stories found matching the criteria.");
            return Command::SUCCESS;
        }

        // Group stories by plot and chapter to avoid duplicate image generation
        $plotChapterGroups = [];
        foreach ($stories as $story) {
            $plotId = $story->getPlot()->getId();
            $chapterNumber = $story->getChapterNumber();
            $key = "plot_{$plotId}_chapter_{$chapterNumber}";
            
            if (!isset($plotChapterGroups[$key])) {
                $plotChapterGroups[$key] = [
                    'plot' => $story->getPlot(),
                    'chapterNumber' => $chapterNumber,
                    'stories' => []
                ];
            }
            $plotChapterGroups[$key]['stories'][] = $story;
        }

        $io->text("Grouped into <info>" . count($plotChapterGroups) . " unique plot/chapter combinations</info>");

        $totalImagesGenerated = 0;
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($plotChapterGroups as $key => $group) {
            $plot = $group['plot'];
            $chapterNumber = $group['chapterNumber'];
            $representativeStory = $group['stories'][0]; // Use first story as representative
            
            $io->section("Processing plot/chapter: {$plot->getTitle()} - Chapter {$chapterNumber}");
            $io->text("    Affects <info>" . count($group['stories']) . " age groups</info>: " . implode(', ', array_map(fn($s) => $s->getAgeGroup(), $group['stories'])));
            
            $vocabulary = $representativeStory->getVocabulary() ?? [];
            $imagePrompts = $vocabulary['image_prompts'] ?? [];

            if (empty($imagePrompts)) {
                $io->text("    ⚠️  <comment>No image prompts found</comment> for this story. Run story generation with --generate-images first.");
                continue;
            }

            $io->text("    Found <info>" . count($imagePrompts) . " image prompts</info>");

            // Check if images already exist for this plot/chapter combination
            $existingImages = $vocabulary['shared_images'] ?? [];
            $imagesExist = !empty($existingImages);
            
            if ($imagesExist && !$replaceExisting) {
                $io->text("    ⏭️  <comment>Skipping images (already exist for this plot/chapter)</comment>");
                $skippedCount += count($imagePrompts);
                continue;
            }
            
            if ($imagesExist && $replaceExisting) {
                $io->text("    🔄 <comment>Replacing existing images for this plot/chapter</comment>");
            }

            $generatedImages = [];

            foreach ($imagePrompts as $index => $prompt) {
                $imageNumber = $index + 1;
                $io->text("    Generating image {$imageNumber} of " . count($imagePrompts));
                
                try {
                    $imageData = $this->generateImageFromPrompt($prompt, $representativeStory, $imageNumber, $imageSize, $io);
                    
                    if ($imageData) {
                        $generatedImages[$imageNumber] = $imageData;
                        
                        if ($saveToDisk) {
                            $this->saveImageToDisk($plot, $chapterNumber, $imageNumber, $imageData, $io);
                        }
                        
                        $totalImagesGenerated++;
                        $io->text("      ✓ Generated image {$imageNumber}");
                    } else {
                        $errorCount++;
                        $io->text("      ✗ Failed to generate image {$imageNumber}");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $io->text("      ✗ Error generating image {$imageNumber}: {$e->getMessage()}");
                }
            }

            // Save images to all stories in this group
            if (!empty($generatedImages)) {
                $this->saveSharedImagesToStories($group['stories'], $generatedImages, $io);
                $successCount++;
            }
        }

        $io->success([
            "Story images generated successfully!",
            "Successfully processed: {$successCount} plot/chapter combinations",
            "Generated: {$totalImagesGenerated} new images",
            "Skipped: {$skippedCount} existing images",
            "Failed: {$errorCount} images",
            "Replace mode: " . ($replaceExisting ? "Enabled" : "Disabled"),
            "Image size: {$imageSize}",
            "Images saved to: " . ($saveToDisk ? "Database and disk" : "Database only"),
            "Images are now shared across all age groups for the same plot/chapter"
        ]);

        return Command::SUCCESS;
    }

    private function generateImageFromPrompt(string $prompt, GenreStory $story, int $imageNumber, string $imageSize, SymfonyStyle $io): ?array
    {
        $genreName = $story->getPlot()->getGenre()->getGenreName();
        $chapterNumber = $story->getChapterNumber();

        // Enhance the prompt with context (no age-specific styling)
        $enhancedPrompt = $this->enhanceImagePrompt($prompt, $genreName, $chapterNumber);

        // Clean and truncate the prompt to meet DALL-E 3 requirements
        $cleanPrompt = $this->cleanPromptForDalle3($enhancedPrompt);

        try {
            $io->text("        Generating image with prompt: " . substr($cleanPrompt, 0, 100) . "...");
            $io->text("        Full prompt: " . $cleanPrompt);
            $io->text("        Prompt length: " . strlen($cleanPrompt) . " characters");
            
            $requestData = [
                'model' => 'dall-e-3',
                'prompt' => $cleanPrompt,
                'size' => $imageSize,
                'quality' => 'standard',
                'n' => 1
            ];
            
            $io->text("        Request data: " . json_encode($requestData));
            
            $response = $this->openAIService->getClient()->request('POST', 'https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIService->getApiKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData
            ]);

            $data = json_decode($response->getContent(), true);
            
            if (!isset($data['data'][0]['url'])) {
                $io->text("        No image URL received from AI for Chapter {$chapterNumber} - Image {$imageNumber}");
                if (isset($data['error'])) {
                    $io->text("        Error: " . json_encode($data['error']));
                }
                return null;
            }

            $imageUrl = $data['data'][0]['url'];
            $revisedPrompt = $data['data'][0]['revised_prompt'] ?? $cleanPrompt;

            // Download the image
            $imageResponse = $this->openAIService->getClient()->request('GET', $imageUrl);
            $imageContent = $imageResponse->getContent();

            return [
                'url' => $imageUrl,
                'content' => $imageContent,
                'prompt' => $cleanPrompt,
                'revisedPrompt' => $revisedPrompt,
                'size' => $imageSize,
                'format' => 'png'
            ];

        } catch (\Exception $e) {
            $io->text("        Exception generating image for Chapter {$chapterNumber} - Image {$imageNumber}: " . $e->getMessage());
            
            // Log more details about the error
            if (strpos($e->getMessage(), '400') !== false) {
                $io->text("        This appears to be a bad request error. Check the prompt content.");
                $io->text("        Prompt length: " . strlen($cleanPrompt) . " characters");
            }
            
            return null;
        }
    }

    private function cleanPromptForDalle3(string $prompt): string
    {
        // Remove any special characters that might cause issues
        $cleanPrompt = preg_replace('/[^\w\s\-.,!?()]/', '', $prompt);
        
        // Remove extra whitespace and newlines
        $cleanPrompt = preg_replace('/\s+/', ' ', $cleanPrompt);
        
        // Truncate to DALL-E 3 limit (1000 characters)
        if (strlen($cleanPrompt) > 1000) {
            $cleanPrompt = substr($cleanPrompt, 0, 997) . '...';
        }
        
        // Ensure the prompt is not empty
        if (empty(trim($cleanPrompt))) {
            $cleanPrompt = "A beautiful children's story illustration with bright colors and engaging characters";
        }
        
        return trim($cleanPrompt);
    }

    private function enhanceImagePrompt(string $prompt, string $genreName, int $chapterNumber): string
    {
        // Create a universal prompt that works for all age groups
        return "A beautiful children's story illustration showing: {$prompt}. Bright colors, friendly characters, safe for children, engaging for all ages.";
    }

    private function generateSharedImageFilename(\App\Entity\GenrePlot $plot, int $chapterNumber, int $imageNumber): string
    {
        $plotId = $plot->getId();
        $timestamp = time();
        
        return "plot_{$plotId}_ch{$chapterNumber}_img{$imageNumber}_{$timestamp}.png";
    }

    private function saveSharedImagesToStories(array $stories, array $generatedImages, SymfonyStyle $io): void
    {
        foreach ($stories as $story) {
            $vocabulary = $story->getVocabulary() ?? [];
            
            // Store shared images reference
            $vocabulary['shared_images'] = [];
            
            foreach ($generatedImages as $imageNumber => $imageData) {
                $uniqueFilename = $this->generateSharedImageFilename($story->getPlot(), $story->getChapterNumber(), $imageNumber);
                
                $vocabulary['shared_images'][$imageNumber] = [
                    'filename' => $uniqueFilename,
                    'prompt' => $imageData['prompt'],
                    'size' => $imageData['size'],
                    'format' => $imageData['format'],
                    'generatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
                ];
            }
            
            $story->setVocabulary($vocabulary);
            $story->setUpdatedAt(new \DateTime());
        }
        
        $this->entityManager->flush();
        $io->text("      ✓ Saved shared images to <info>" . count($stories) . " age groups</info>");
    }

    private function saveImageToDisk(\App\Entity\GenrePlot $plot, int $chapterNumber, int $imageNumber, array $imageData, SymfonyStyle $io): void
    {
        // Create base directory (keep flat structure)
        $baseDir = "public/assets/story-images";
        
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        
        // Generate unique filename
        $uniqueFilename = $this->generateSharedImageFilename($plot, $chapterNumber, $imageNumber);
        $filepath = "{$baseDir}/{$uniqueFilename}";
        
        file_put_contents($filepath, $imageData['content']);
        
        $io->text("      ✓ Saved image to disk: {$filepath}");
    }
} 