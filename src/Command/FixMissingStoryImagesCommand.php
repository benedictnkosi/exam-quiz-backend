<?php

namespace App\Command;

use App\Entity\GenreStory;
use App\Repository\GenreStoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-missing-story-images',
    description: 'Find and fix stories where an age group is missing images but other age groups have images for the same plot',
)]
class FixMissingStoryImagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GenreStoryRepository $genreStoryRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('plot-id', 'p', InputOption::VALUE_OPTIONAL, 'Fix missing images for a specific plot ID only')
            ->addOption('age-group', 'a', InputOption::VALUE_OPTIONAL, 'Fix missing images for a specific age group only (7-10, 11-14, 15-18)')
            ->addOption('chapter', 'c', InputOption::VALUE_OPTIONAL, 'Fix missing images for a specific chapter number only')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would be fixed without making changes')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of plots to process in each batch (default: 10)', 10)
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'PHP memory limit to set (e.g., 512M, 1G)', '512M')
            ->setHelp('This command finds stories where an age group is missing images but other age groups have images for the same plot/chapter combination. It then copies the vocabulary (including shared_images) from stories that have images to the ones that are missing them. Use --dry-run to see what would be fixed without making changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Set memory limit
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $io->text("Memory limit set to: <info>{$memoryLimit}</info>");
        }
        
        $specificPlotId = $input->getOption('plot-id');
        $specificAgeGroup = $input->getOption('age-group');
        $specificChapter = $input->getOption('chapter');
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('Finding and Fixing Missing Story Images');

        // Validate age group if specified
        if ($specificAgeGroup && !in_array($specificAgeGroup, GenreStory::getAgeGroups())) {
            $io->error("Invalid age group: {$specificAgeGroup}. Valid options are: " . implode(', ', GenreStory::getAgeGroups()));
            return Command::FAILURE;
        }

        // Get plots to process
        $plots = $this->getPlotsToProcess($specificPlotId, $specificAgeGroup, $specificChapter);
        
        if (empty($plots)) {
            $io->warning("No plots found matching the criteria.");
            return Command::SUCCESS;
        }

        $io->text("Found <info>" . count($plots) . " plots</info> to process");
        $io->text("Processing in batches of <info>{$batchSize}</info>");
        
        if ($dryRun) {
            $io->note("DRY RUN MODE - No changes will be made");
        }

        $totalPlotsProcessed = 0;
        $totalStoriesFixed = 0;
        $totalStoriesSkipped = 0;
        $totalErrors = 0;

        // Process plots in batches
        $offset = 0;
        while ($offset < count($plots)) {
            $batchPlots = array_slice($plots, $offset, $batchSize);
            $io->section("Processing batch " . (($offset / $batchSize) + 1) . " (plots " . ($offset + 1) . "-" . min($offset + $batchSize, count($plots)) . ")");
            
            // Process this batch
            $batchResults = $this->processPlotsBatch($batchPlots, $specificAgeGroup, $specificChapter, $dryRun, $io);
            
            $totalPlotsProcessed += $batchResults['plotsProcessed'];
            $totalStoriesFixed += $batchResults['storiesFixed'];
            $totalStoriesSkipped += $batchResults['storiesSkipped'];
            $totalErrors += $batchResults['errors'];

            // Clear entity manager to free memory
            $this->entityManager->clear();
            
            // Force garbage collection
            gc_collect_cycles();
            
            $io->text("Batch completed. Memory usage: <info>" . $this->formatBytes(memory_get_usage(true)) . "</info>");
            
            $offset += $batchSize;
        }

        // Display final results
        $io->section('Final Results');
        $io->text("Total plots processed: <info>{$totalPlotsProcessed}</info>");
        $io->text("Total stories fixed: <info>{$totalStoriesFixed}</info>");
        $io->text("Total stories skipped (no issues): <info>{$totalStoriesSkipped}</info>");
        $io->text("Total errors: <info>{$totalErrors}</info>");

        if ($totalErrors > 0) {
            $io->warning("Some operations failed. Check the logs above for details.");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->success("Dry run completed! Use without --dry-run to apply the fixes.");
        } else {
            $io->success("Missing story images fixed successfully!");
        }
        
        return Command::SUCCESS;
    }

    private function getPlotsToProcess(?string $specificPlotId, ?string $specificAgeGroup, ?string $specificChapter): array
    {
        if ($specificPlotId) {
            $plot = $this->entityManager->getRepository(\App\Entity\GenrePlot::class)->find($specificPlotId);
            return $plot ? [$plot] : [];
        }

        // Get all plots that have stories
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT p')
           ->from(\App\Entity\GenrePlot::class, 'p')
           ->join(\App\Entity\GenreStory::class, 's', 'WITH', 's.plot = p')
           ->where('s.isActive = :isActive')
           ->setParameter('isActive', true);

        if ($specificAgeGroup) {
            $qb->andWhere('s.ageGroup = :ageGroup')
               ->setParameter('ageGroup', $specificAgeGroup);
        }

        if ($specificChapter) {
            $qb->andWhere('s.chapterNumber = :chapterNumber')
               ->setParameter('chapterNumber', $specificChapter);
        }

        return $qb->getQuery()->getResult();
    }

    private function processPlotsBatch(array $plots, ?string $specificAgeGroup, ?string $specificChapter, bool $dryRun, SymfonyStyle $io): array
    {
        $plotsProcessed = 0;
        $storiesFixed = 0;
        $storiesSkipped = 0;
        $errors = 0;

        foreach ($plots as $plot) {
            $io->text("Processing plot: <info>{$plot->getTitle()}</info> (ID: {$plot->getId()})");
            
            try {
                $plotResults = $this->processPlot($plot, $specificAgeGroup, $specificChapter, $dryRun, $io);
                
                $plotsProcessed++;
                $storiesFixed += $plotResults['storiesFixed'];
                $storiesSkipped += $plotResults['storiesSkipped'];
                
            } catch (\Exception $e) {
                $errors++;
                $io->text("  ✗ Error processing plot {$plot->getId()}: {$e->getMessage()}");
            }
        }

        return [
            'plotsProcessed' => $plotsProcessed,
            'storiesFixed' => $storiesFixed,
            'storiesSkipped' => $storiesSkipped,
            'errors' => $errors
        ];
    }

    private function processPlot(\App\Entity\GenrePlot $plot, ?string $specificAgeGroup, ?string $specificChapter, bool $dryRun, SymfonyStyle $io): array
    {
        $storiesFixed = 0;
        $storiesSkipped = 0;

        // Get all stories for this plot
        $stories = $this->genreStoryRepository->findByPlot($plot);
        
        if (empty($stories)) {
            $io->text("  ⚠️  No stories found for this plot");
            return ['storiesFixed' => 0, 'storiesSkipped' => 0];
        }

        // Filter stories by criteria
        $filteredStories = array_filter($stories, function($story) use ($specificAgeGroup, $specificChapter) {
            if ($specificAgeGroup && $story->getAgeGroup() !== $specificAgeGroup) {
                return false;
            }
            if ($specificChapter && $story->getChapterNumber() != $specificChapter) {
                return false;
            }
            return true;
        });

        if (empty($filteredStories)) {
            $io->text("  ⚠️  No stories match the specified criteria");
            return ['storiesFixed' => 0, 'storiesSkipped' => 0];
        }

        // Group stories by chapter
        $storiesByChapter = [];
        foreach ($filteredStories as $story) {
            $chapterNumber = $story->getChapterNumber();
            if (!isset($storiesByChapter[$chapterNumber])) {
                $storiesByChapter[$chapterNumber] = [];
            }
            $storiesByChapter[$chapterNumber][] = $story;
        }

        foreach ($storiesByChapter as $chapterNumber => $chapterStories) {
            $io->text("  Processing Chapter {$chapterNumber}");
            
            // Check which age groups have images and which don't
            $storiesWithImages = [];
            $storiesWithoutImages = [];
            
            foreach ($chapterStories as $story) {
                $vocabulary = $story->getVocabulary() ?? [];
                $hasImages = !empty($vocabulary['shared_images'] ?? []);
                
                if ($hasImages) {
                    $storiesWithImages[] = $story;
                } else {
                    $storiesWithoutImages[] = $story;
                }
            }
            
            $io->text("    Age groups with images: <info>" . count($storiesWithImages) . "</info>");
            $io->text("    Age groups without images: <info>" . count($storiesWithoutImages) . "</info>");
            
            if (empty($storiesWithImages)) {
                $io->text("    ⚠️  No age groups have images for this chapter");
                $storiesSkipped += count($chapterStories);
                continue;
            }
            
            if (empty($storiesWithoutImages)) {
                $io->text("    ✓ All age groups have images for this chapter");
                $storiesSkipped += count($chapterStories);
                continue;
            }
            
            // Check if we actually have missing images (some age groups have images, others don't)
            if (count($storiesWithImages) === count($chapterStories)) {
                $io->text("    ✓ All age groups have images for this chapter");
                $storiesSkipped += count($chapterStories);
                continue;
            }
            
            // Use the first story with images as the source
            $sourceStory = $storiesWithImages[0];
            $sourceVocabulary = $sourceStory->getVocabulary() ?? [];
            
            $io->text("    Using age group <info>{$sourceStory->getAgeGroup()}</info> as source for images");
            $io->text("    Source has <info>" . count($sourceVocabulary['shared_images'] ?? []) . " images</info>");
            
            // Copy vocabulary to stories without images
            foreach ($storiesWithoutImages as $targetStory) {
                $io->text("    Fixing age group <info>{$targetStory->getAgeGroup()}</info>");
                
                if (!$dryRun) {
                    $targetVocabulary = $targetStory->getVocabulary() ?? [];
                    $targetVocabulary['shared_images'] = $sourceVocabulary['shared_images'] ?? [];
                    
                    $targetStory->setVocabulary($targetVocabulary);
                    $targetStory->setUpdatedAt(new \DateTime());
                }
                
                $storiesFixed++;
            }
        }
        
        if (!$dryRun && $storiesFixed > 0) {
            $this->entityManager->flush();
            $io->text("  ✓ Saved changes for <info>{$storiesFixed}</info> stories");
        }

        return [
            'storiesFixed' => $storiesFixed,
            'storiesSkipped' => $storiesSkipped
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
} 