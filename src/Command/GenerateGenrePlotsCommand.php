<?php

namespace App\Command;

use App\Entity\GenrePlot;
use App\Entity\ReadGenres;
use App\Repository\GenrePlotRepository;
use App\Repository\ReadGenresRepository;
use App\Service\OpenAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-genre-plots',
    description: 'Generate engaging plots for each reading genre using AI',
)]
class GenerateGenrePlotsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReadGenresRepository $readGenresRepository,
        private GenrePlotRepository $genrePlotRepository,
        private OpenAIService $openAIService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('genre', 'g', InputOption::VALUE_OPTIONAL, 'Generate plots for a specific genre only')
            ->addOption('replace', 'r', InputOption::VALUE_NONE, 'Replace existing plots for the genre(s)')
            ->setHelp('This command generates 5 engaging plots for each subcategory within each reading genre using AI and saves them to the database. Use --genre to generate for a specific genre only. Use --replace to delete existing plots before generating new ones.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specificGenre = $input->getOption('genre');
        $replaceExisting = $input->getOption('replace');

        $io->title('Generating Genre Plots with AI');

        // Get genres to process
        if ($specificGenre) {
            $genre = $this->readGenresRepository->findByGenreName($specificGenre);
            if (!$genre) {
                $io->error("Genre '{$specificGenre}' not found.");
                return Command::FAILURE;
            }
            $genres = [$genre];
            $io->text("Generating plots for specific genre: <info>{$specificGenre}</info>");
        } else {
            $genres = $this->readGenresRepository->findActiveGenres();
            $io->text("Generating plots for all active genres: <info>" . count($genres) . " genres</info>");
        }

        $totalPlotsCreated = 0;
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($genres as $genre) {
            $io->section("Processing genre: {$genre->getGenreName()}");
            
            $subcategories = $genre->getSubcategories();
            if (!$subcategories || empty($subcategories)) {
                $io->text("⚠️  <comment>No subcategories found</comment> for {$genre->getGenreName()}. Skipping.");
                continue;
            }

            $genreSuccessCount = 0;
            $genreErrorCount = 0;
            $genreSkippedCount = 0;

            foreach ($subcategories as $subcategory) {
                $io->text("  Processing subcategory: <info>{$subcategory}</info>");
                
                // Check if plots already exist for this subcategory
                $existingCount = $this->genrePlotRepository->countByGenreAndSubcategory($genre, $subcategory);
                if ($existingCount > 0) {
                    if ($replaceExisting) {
                        $deletedCount = $this->genrePlotRepository->deleteByGenreAndSubcategory($genre, $subcategory);
                        $io->text("    Deleted <info>{$deletedCount} existing plots</info> for {$subcategory}");
                    } else {
                        $io->text("    ⏭️  <comment>Skipping {$subcategory}</comment> - {$existingCount} plots already exist");
                        $genreSkippedCount++;
                        $skippedCount++;
                        continue;
                    }
                }
                
                try {
                    $plots = $this->generatePlotsForSubcategory($genre, $subcategory, $io);
                    
                    if ($plots) {
                        $plotsCreated = $this->savePlotsToDatabase($genre, $subcategory, $plots);
                        $totalPlotsCreated += $plotsCreated;
                        $genreSuccessCount++;
                        $io->text("    ✓ Generated and saved <info>{$plotsCreated} plots</info> for {$subcategory}");
                    } else {
                        $genreErrorCount++;
                        $errorCount++;
                        $io->text("    ✗ Failed to generate plots for {$subcategory}");
                    }
                } catch (\Exception $e) {
                    $genreErrorCount++;
                    $errorCount++;
                    $io->text("    ✗ Error generating plots for {$subcategory}: {$e->getMessage()}");
                }
            }

            if ($genreSuccessCount > 0) {
                $successCount++;
            }
            
            $io->text("  Genre summary: <info>{$genreSuccessCount} subcategories</info> successful, <comment>{$genreErrorCount} failed</comment>, <comment>{$genreSkippedCount} skipped</comment>");
        }

        $io->success([
            "Genre plots generation completed!",
            "Successfully processed: {$successCount} genres",
            "Failed: {$errorCount} subcategories",
            "Skipped: {$skippedCount} subcategories (already exist)",
            "Total plots created: {$totalPlotsCreated}",
            "Plots saved to database (5 per subcategory)"
        ]);

        return Command::SUCCESS;
    }

    private function generatePlotsForSubcategory(ReadGenres $genre, string $subcategory, SymfonyStyle $io): ?array
    {
        $genreName = $genre->getGenreName();

        $prompt = "You are a creative storyteller specializing in creating engaging plots for young readers. Generate 5 unique and captivating plot ideas specifically for the '{$subcategory}' subcategory within the '{$genreName}' genre.

Genre: {$genreName}
Subcategory: {$subcategory}

For each plot, create:
1. A compelling title
2. A brief but engaging synopsis (2-3 sentences)
3. The main conflict or challenge
4. Key characters (protagonist and supporting characters)
5. The setting/location
6. A hook that would grab a young reader's attention

Requirements:
- Each plot should be suitable for young readers (ages 8-16)
- All plots should focus specifically on the '{$subcategory}' theme
- Make each plot unique and memorable within this subcategory
- Focus on relatable characters and situations
- Include elements of adventure, discovery, or personal growth
- Ensure plots are age-appropriate and engaging
- Stay true to the subcategory theme while being creative

Format your response as a JSON array with 5 objects, each containing:
{
  \"title\": \"Plot Title\",
  \"synopsis\": \"Brief engaging description\",
  \"conflict\": \"Main challenge or problem\",
  \"characters\": \"Key characters description\",
  \"setting\": \"Where the story takes place\",
  \"hook\": \"Attention-grabbing opening idea\"
}

Return ONLY the JSON array, no additional text or explanations.";

        try {
            $response = $this->openAIService->getClient()->request('POST', $this->openAIService->getApiUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIService->getApiKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a creative storyteller who generates engaging plot ideas for young readers. You create diverse, age-appropriate, and captivating story concepts that inspire imagination and reading engagement. You specialize in creating plots that focus on specific subcategories within genres.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.8,
                    'max_tokens' => 2000
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                $io->text("    No content received from AI for {$subcategory}");
                return null;
            }

            // Clean the response to extract JSON
            $content = trim($content);
            
            // Remove any markdown formatting if present
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $plots = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->text("    JSON parsing error for {$subcategory}: " . json_last_error_msg());
                $io->text("    Raw content: " . substr($content, 0, 200) . "...");
                return null;
            }

            if (!is_array($plots) || count($plots) !== 5) {
                $io->text("    Invalid plot count for {$subcategory}. Expected 5, got " . count($plots));
                return null;
            }

            // Validate each plot has required fields
            foreach ($plots as $index => $plot) {
                $requiredFields = ['title', 'synopsis', 'conflict', 'characters', 'setting', 'hook'];
                foreach ($requiredFields as $field) {
                    if (!isset($plot[$field]) || empty($plot[$field])) {
                        $io->text("    Plot " . ($index + 1) . " missing required field: {$field}");
                        return null;
                    }
                }
            }

            return $plots;

        } catch (\Exception $e) {
            $io->text("    Exception generating plots for {$subcategory}: " . $e->getMessage());
            return null;
        }
    }

    private function savePlotsToDatabase(ReadGenres $genre, string $subcategory, array $plots): int
    {
        $plotsCreated = 0;

        foreach ($plots as $plotData) {
            $plot = new GenrePlot();
            $plot->setGenre($genre);
            $plot->setSubcategory($subcategory);
            $plot->setTitle($plotData['title']);
            $plot->setSynopsis($plotData['synopsis']);
            $plot->setConflict($plotData['conflict']);
            $plot->setCharacters($plotData['characters']);
            $plot->setSetting($plotData['setting']);
            $plot->setHook($plotData['hook']);

            $this->entityManager->persist($plot);
            $plotsCreated++;
        }

        $this->entityManager->flush();
        return $plotsCreated;
    }
} 