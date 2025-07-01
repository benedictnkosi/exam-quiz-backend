<?php

namespace App\Command;

use App\Entity\GenrePlot;
use App\Entity\GenreStory;
use App\Repository\GenrePlotRepository;
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
    name: 'app:generate-genre-stories',
    description: 'Generate stories for each plot at different age levels using AI',
)]
class GenerateGenreStoriesCommand extends Command
{
    private const CHAPTERS_PER_STORY = 5;
    private const IMAGES_PER_CHAPTER = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GenrePlotRepository $genrePlotRepository,
        private GenreStoryRepository $genreStoryRepository,
        private OpenAIService $openAIService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('plot', 'p', InputOption::VALUE_OPTIONAL, 'Generate stories for a specific plot ID only')
            ->addOption('genre', 'g', InputOption::VALUE_OPTIONAL, 'Generate stories for all plots in a specific genre')
            ->addOption('age-group', 'a', InputOption::VALUE_OPTIONAL, 'Generate stories for a specific age group only (7-10, 11-14, 15-18)')
            ->addOption('replace', 'r', InputOption::VALUE_NONE, 'Replace existing stories for the plot(s)')
            ->addOption('chapters', 'c', InputOption::VALUE_OPTIONAL, 'Number of chapters per story (default: 5)', 5)
            ->addOption('generate-images', 'i', InputOption::VALUE_NONE, 'Generate image prompts for each chapter')
            ->addOption('generate-quiz', 'z', InputOption::VALUE_NONE, 'Generate quiz questions for each chapter')
            ->setHelp('This command generates stories for each plot at different age levels using AI. Each story consists of multiple chapters with accumulative summaries and optional image placeholders. By default, the command skips plots that already have stories generated. Use --plot to generate for a specific plot, --genre for all plots in a genre, or --age-group for a specific age group. Use --replace to delete existing stories before generating new ones. Use --generate-images to include image prompts. Use --generate-quiz to include quiz questions.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specificPlotId = $input->getOption('plot');
        $specificGenre = $input->getOption('genre');
        $specificAgeGroup = $input->getOption('age-group');
        $replaceExisting = $input->getOption('replace');
        $chaptersPerStory = (int) $input->getOption('chapters');
        $generateImages = $input->getOption('generate-images');
        $generateQuiz = $input->getOption('generate-quiz');

        $io->title('Generating Genre Stories with AI');

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
            $io->text("Generating stories for specific plot: <info>{$plot->getTitle()}</info>");
        } elseif ($specificGenre) {
            $plots = $this->genrePlotRepository->findByGenreName($specificGenre);
            if (empty($plots)) {
                $io->error("No plots found for genre: {$specificGenre}");
                return Command::FAILURE;
            }
            $io->text("Generating stories for all plots in genre: <info>{$specificGenre}</info> (<info>" . count($plots) . " plots</info>)");
        } else {
            $plots = $this->genrePlotRepository->findActivePlots();
            $io->text("Generating stories for all active plots: <info>" . count($plots) . " plots</info>");
        }

        $ageGroups = $specificAgeGroup ? [$specificAgeGroup] : GenreStory::getAgeGroups();
        $totalChaptersCreated = 0;
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($plots as $plot) {
            $io->section("Processing plot: {$plot->getTitle()} ({$plot->getGenre()->getGenreName()})");
            
            foreach ($ageGroups as $ageGroup) {
                $io->text("  Processing age group: <info>{$ageGroup}</info>");
                
                // Check if story already exists
                $existingChapters = $this->genreStoryRepository->countChaptersByPlotAndAgeGroup($plot, $ageGroup);
                if ($existingChapters > 0) {
                    if ($replaceExisting) {
                        $deletedCount = $this->genreStoryRepository->deleteByPlot($plot);
                        $io->text("    Deleted {$existingChapters} existing chapters for age group {$ageGroup}");
                    } else {
                        $io->text("    ⏭️  <comment>Skipped {$existingChapters} existing chapters</comment> for age group {$ageGroup}. Use --replace to regenerate.");
                        $skippedCount++;
                        continue;
                    }
                }
                
                try {
                    $chaptersCreated = $this->generateCompleteStory($plot, $ageGroup, $chaptersPerStory, $generateImages, $generateQuiz, $io);
                    $totalChaptersCreated += $chaptersCreated;
                    $io->text("    ✓ Generated and saved <info>{$chaptersCreated} chapters</info> for age group {$ageGroup}");
                } catch (\Exception $e) {
                    $errorCount++;
                    $io->text("    ✗ Error generating story for age group {$ageGroup}: {$e->getMessage()}");
                }
            }
            
            $successCount++;
        }

        $io->success([
            "Genre stories processing completed!",
            "Successfully processed: {$successCount} plots",
            "Skipped existing stories: {$skippedCount}",
            "Failed: {$errorCount} stories",
            "Total chapters created: {$totalChaptersCreated}",
            "Chapters per story: {$chaptersPerStory}",
            "Images per chapter: " . ($generateImages ? self::IMAGES_PER_CHAPTER : 0),
            "Quiz generation: " . ($generateQuiz ? "Enabled" : "Disabled"),
            "Age groups processed: " . implode(', ', $ageGroups),
            "Stories saved to database"
        ]);

        return Command::SUCCESS;
    }

    private function generateCompleteStory(GenrePlot $plot, string $ageGroup, int $chaptersPerStory, bool $generateImages, bool $generateQuiz, SymfonyStyle $io): int
    {
        $chaptersCreated = 0;
        $accumulativeSummary = '';

        for ($chapterNumber = 1; $chapterNumber <= $chaptersPerStory; $chapterNumber++) {
            $io->text("    Generating chapter {$chapterNumber} of {$chaptersPerStory}");
            
            try {
                $chapterData = $this->generateChapterForPlot($plot, $ageGroup, $chapterNumber, $accumulativeSummary, $generateImages, $generateQuiz, $io);
                
                if ($chapterData) {
                    $story = $this->saveChapterToDatabase($plot, $ageGroup, $chapterNumber, $chapterData);
                    $chaptersCreated++;
                    
                    // Update accumulative summary for next chapter
                    $accumulativeSummary = $chapterData['accumulativeSummary'];
                    
                    $io->text("      ✓ Generated chapter {$chapterNumber} (<info>{$story->getWordCount()} words</info>)");
                    
                    if ($generateImages && isset($chapterData['imagePrompts'])) {
                        $io->text("      ✓ Generated <info>" . count($chapterData['imagePrompts']) . " image prompts</info> for chapter {$chapterNumber}");
                    }
                    
                    if ($generateQuiz && isset($chapterData['quiz'])) {
                        $quizCount = count($chapterData['quiz']['questions'] ?? []);
                        $io->text("      ✓ Generated <info>{$quizCount} quiz questions</info> for chapter {$chapterNumber}");
                    }
                } else {
                    $io->text("      ✗ Failed to generate chapter {$chapterNumber}");
                    break; // Stop if a chapter fails
                }
            } catch (\Exception $e) {
                $io->text("      ✗ Error generating chapter {$chapterNumber}: {$e->getMessage()}");
                break; // Stop if a chapter fails
            }
        }

        return $chaptersCreated;
    }

    private function generateChapterForPlot(GenrePlot $plot, string $ageGroup, int $chapterNumber, string $previousSummary, bool $generateImages, bool $generateQuiz, SymfonyStyle $io): ?array
    {
        $genreName = $plot->getGenre()->getGenreName();
        $plotTitle = $plot->getTitle();
        $plotSynopsis = $plot->getSynopsis();
        $plotConflict = $plot->getConflict();
        $plotCharacters = $plot->getCharacters();
        $plotSetting = $plot->getSetting();
        $plotHook = $plot->getHook();

        // Define age-specific requirements
        $ageRequirements = $this->getAgeGroupRequirements($ageGroup);

        // Build chapter-specific prompt
        $chapterPrompt = $this->getChapterPrompt($chapterNumber, $ageGroup);

        $imageInstructions = $generateImages ? $this->getImageInstructions($ageGroup) : '';
        $quizInstructions = $generateQuiz ? $this->getQuizInstructions($ageGroup) : '';

        $prompt = "You are a creative storyteller specializing in writing engaging stories for young readers. Create Chapter {$chapterNumber} of a {$ageGroup} age group story based on the following plot details.

Genre: {$genreName}
Age Group: {$ageGroup}
Plot Title: {$plotTitle}
Plot Synopsis: {$plotSynopsis}
Main Conflict: {$plotConflict}
Characters: {$plotCharacters}
Setting: {$plotSetting}
Hook: {$plotHook}

{$ageRequirements}

{$chapterPrompt}

" . ($previousSummary ? "Previous Chapters Summary:\n{$previousSummary}\n" : "") . "

{$imageInstructions}

{$quizInstructions}

Requirements:
- Write an engaging chapter that follows the plot outline and chapter goals
- Use vocabulary and sentence structure appropriate for {$ageGroup} age group
- Include dialogue that feels natural for the target age
- Create vivid descriptions that engage the reader's imagination
- Ensure the chapter has a clear beginning, middle, and end
- Include elements of the genre and subcategories where relevant
- Make the story relatable and age-appropriate
- Include moments of tension, discovery, and resolution
- End with a hook that makes readers want to continue
- Keep the chapter engaging from start to finish
- Build upon previous chapters if this is not the first chapter" . ($generateImages ? "
- IMPORTANT: Place [IMAGE_PLACEHOLDER_1] and [IMAGE_PLACEHOLDER_2] at strategic moments WITHIN the story content, NOT at the end
- Place [IMAGE_PLACEHOLDER_1] after introducing the main setting or characters (early in the chapter)
- Place [IMAGE_PLACEHOLDER_2] at a moment of action, discovery, emotion, or dramatic tension (middle or later in the chapter)
- Do NOT place both placeholders at the end of the chapter
- Integrate the placeholders naturally into the narrative flow" : "") . ($generateQuiz ? "
- Create 5 quiz questions based on the chapter content to test reading comprehension
- Questions should be age-appropriate and cover different aspects of the story
- Include multiple choice questions with 4 options each
- Ensure one option is clearly the correct answer
- Questions should test understanding of plot, characters, setting, and vocabulary" : "") . "

Format your response as follows:

[CHAPTER]
[Your complete chapter content here with image placeholders strategically placed within the narrative, not at the end]

[SUMMARY]
[Brief 2-3 sentence summary of this chapter]

[ACCUMULATIVE_SUMMARY]
[Complete summary of the entire story so far, including this chapter]

[VOCABULARY]
[\"word1\", \"word2\", \"word3\"]
[List of key vocabulary words used in this chapter]" . ($generateImages ? "

[IMAGE_PROMPTS]
[IMAGE_1]
[Detailed description for the first image in this chapter]

[IMAGE_2]
[Detailed description for the second image in this chapter]" : "") . ($generateQuiz ? "

[QUIZ]
{
  \"questions\": [
    {
      \"question\": \"What is the question text?\",
      \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
      \"correct_answer\": 0,
      \"explanation\": \"Brief explanation of why this is the correct answer\"
    }
  ]
}" : "") . "

Return ONLY the formatted response, no additional text or explanations.";

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
                            'content' => 'You are a skilled children\'s author who creates engaging, age-appropriate stories that inspire imagination and reading engagement. You adapt your writing style, vocabulary, and themes to match the developmental level and interests of your target age group. You excel at creating chaptered stories that build upon each other with clear narrative progression. You also understand how to strategically place image placeholders and create detailed image prompts that enhance the storytelling experience. When placing image placeholders, you integrate them naturally into the narrative at key moments, not at the end of the chapter.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.8,
                    'max_tokens' => 4000
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                $io->text("        No content received from AI for {$plotTitle} - Chapter {$chapterNumber}");
                return null;
            }

            // Parse the response
            if ($generateImages && $generateQuiz) {
                preg_match('/\[CHAPTER\](.*?)\[SUMMARY\](.*?)\[ACCUMULATIVE_SUMMARY\](.*?)\[VOCABULARY\](.*?)\[IMAGE_PROMPTS\](.*?)\[QUIZ\](.*?)$/s', $content, $matches);
                
                if (count($matches) < 7) {
                    $io->text("        Failed to parse AI response for {$plotTitle} - Chapter {$chapterNumber}");
                    return null;
                }

                $chapter = trim($matches[1]);
                $summary = trim($matches[2]);
                $accumulativeSummary = trim($matches[3]);
                $vocabularyText = trim($matches[4]);
                $imagePromptsText = trim($matches[5]);
                $quizText = trim($matches[6]);

                // Parse image prompts
                preg_match('/\[IMAGE_1\](.*?)\[IMAGE_2\](.*?)$/s', $imagePromptsText, $imageMatches);
                $imagePrompts = [];
                if (count($imageMatches) >= 3) {
                    $imagePrompts[] = trim($imageMatches[1]);
                    $imagePrompts[] = trim($imageMatches[2]);
                }

                // Parse quiz
                $quiz = json_decode($quizText, true);
                if (!$quiz || !isset($quiz['questions'])) {
                    $io->text("        Failed to parse quiz JSON for {$plotTitle} - Chapter {$chapterNumber}");
                    $quiz = null;
                }

                // Validate image placeholder placement
                $this->validateImagePlaceholderPlacement($chapter, $chapterNumber, $io);
            } elseif ($generateImages) {
                preg_match('/\[CHAPTER\](.*?)\[SUMMARY\](.*?)\[ACCUMULATIVE_SUMMARY\](.*?)\[VOCABULARY\](.*?)\[IMAGE_PROMPTS\](.*?)$/s', $content, $matches);
                
                if (count($matches) < 6) {
                    $io->text("        Failed to parse AI response for {$plotTitle} - Chapter {$chapterNumber}");
                    return null;
                }

                $chapter = trim($matches[1]);
                $summary = trim($matches[2]);
                $accumulativeSummary = trim($matches[3]);
                $vocabularyText = trim($matches[4]);
                $imagePromptsText = trim($matches[5]);

                // Parse image prompts
                preg_match('/\[IMAGE_1\](.*?)\[IMAGE_2\](.*?)$/s', $imagePromptsText, $imageMatches);
                $imagePrompts = [];
                if (count($imageMatches) >= 3) {
                    $imagePrompts[] = trim($imageMatches[1]);
                    $imagePrompts[] = trim($imageMatches[2]);
                }

                $quiz = null;

                // Validate image placeholder placement
                $this->validateImagePlaceholderPlacement($chapter, $chapterNumber, $io);
            } elseif ($generateQuiz) {
                preg_match('/\[CHAPTER\](.*?)\[SUMMARY\](.*?)\[ACCUMULATIVE_SUMMARY\](.*?)\[VOCABULARY\](.*?)\[QUIZ\](.*?)$/s', $content, $matches);
                
                if (count($matches) < 6) {
                    $io->text("        Failed to parse AI response for {$plotTitle} - Chapter {$chapterNumber}");
                    return null;
                }

                $chapter = trim($matches[1]);
                $summary = trim($matches[2]);
                $accumulativeSummary = trim($matches[3]);
                $vocabularyText = trim($matches[4]);
                $quizText = trim($matches[5]);

                $imagePrompts = [];

                // Parse quiz
                $quiz = json_decode($quizText, true);
                if (!$quiz || !isset($quiz['questions'])) {
                    $io->text("        Failed to parse quiz JSON for {$plotTitle} - Chapter {$chapterNumber}");
                    $quiz = null;
                }
            } else {
                preg_match('/\[CHAPTER\](.*?)\[SUMMARY\](.*?)\[ACCUMULATIVE_SUMMARY\](.*?)\[VOCABULARY\](.*?)$/s', $content, $matches);
                
                if (count($matches) < 5) {
                    $io->text("        Failed to parse AI response for {$plotTitle} - Chapter {$chapterNumber}");
                    return null;
                }

                $chapter = trim($matches[1]);
                $summary = trim($matches[2]);
                $accumulativeSummary = trim($matches[3]);
                $vocabularyText = trim($matches[4]);
                $imagePrompts = [];
                $quiz = null;
            }

            // Parse vocabulary
            $vocabulary = json_decode($vocabularyText, true);
            if (!is_array($vocabulary)) {
                $vocabulary = [];
            }

            // Calculate word count and reading time
            $wordCount = str_word_count($chapter);
            $readingTime = ceil($wordCount / 200); // Assuming 200 words per minute

            return [
                'content' => $chapter,
                'summary' => $summary,
                'accumulativeSummary' => $accumulativeSummary,
                'wordCount' => $wordCount,
                'readingTime' => $readingTime,
                'vocabulary' => $vocabulary,
                'imagePrompts' => $imagePrompts,
                'quiz' => $quiz
            ];

        } catch (\Exception $e) {
            $io->text("        Exception generating chapter for {$plotTitle} - Chapter {$chapterNumber}: " . $e->getMessage());
            return null;
        }
    }

    private function validateImagePlaceholderPlacement(string $chapter, int $chapterNumber, SymfonyStyle $io): void
    {
        // Check if placeholders are at the end
        $lines = explode("\n", $chapter);
        $lastLines = array_slice($lines, -3); // Check last 3 lines
        $lastContent = implode("\n", $lastLines);
        
        if (strpos($lastContent, '[IMAGE_PLACEHOLDER_1]') !== false || 
            strpos($lastContent, '[IMAGE_PLACEHOLDER_2]') !== false) {
            $io->text("        ⚠️  <comment>Warning: Image placeholders may be placed at the end of Chapter {$chapterNumber}</comment>");
        }
        
        // Check if both placeholders are together
        if (strpos($chapter, '[IMAGE_PLACEHOLDER_1]  [IMAGE_PLACEHOLDER_2]') !== false ||
            strpos($chapter, '[IMAGE_PLACEHOLDER_2]  [IMAGE_PLACEHOLDER_1]') !== false) {
            $io->text("        ⚠️  <comment>Warning: Both image placeholders are placed together in Chapter {$chapterNumber}</comment>");
        }
        
        // Check if placeholders are properly spaced
        $placeholder1Pos = strpos($chapter, '[IMAGE_PLACEHOLDER_1]');
        $placeholder2Pos = strpos($chapter, '[IMAGE_PLACEHOLDER_2]');
        
        if ($placeholder1Pos !== false && $placeholder2Pos !== false) {
            $distance = abs($placeholder2Pos - $placeholder1Pos);
            if ($distance < 100) { // Less than 100 characters apart
                $io->text("        ⚠️  <comment>Warning: Image placeholders are very close together in Chapter {$chapterNumber}</comment>");
            }
        }
    }

    private function getImageInstructions(string $ageGroup): string
    {
        switch ($ageGroup) {
            case GenreStory::AGE_GROUP_7_10:
                return "Image Instructions for Age Group 7-10:
- Place [IMAGE_PLACEHOLDER_1] early in the chapter after introducing the main setting or characters
- Place [IMAGE_PLACEHOLDER_2] at a moment of action, discovery, or emotion in the middle or later part of the chapter
- DO NOT place both placeholders at the end of the chapter
- Create image prompts that are colorful, simple, and engaging for young readers
- Focus on bright colors, friendly characters, and clear visual elements
- Avoid complex or scary imagery
- Include descriptive details about characters, setting, and mood
- Ensure placeholders are naturally integrated into the story flow";

            case GenreStory::AGE_GROUP_11_14:
                return "Image Instructions for Age Group 11-14:
- Place [IMAGE_PLACEHOLDER_1] early in the chapter at a scene-setting or character introduction moment
- Place [IMAGE_PLACEHOLDER_2] at a moment of conflict, discovery, or character development in the middle or later part
- DO NOT place both placeholders at the end of the chapter
- Create image prompts that are dynamic and engaging for middle-grade readers
- Include atmospheric details, character expressions, and environmental elements
- Balance action with emotional moments
- Use descriptive language that creates vivid mental images
- Ensure placeholders are strategically placed to enhance the narrative";

            case GenreStory::AGE_GROUP_15_18:
                return "Image Instructions for Age Group 15-18:
- Place [IMAGE_PLACEHOLDER_1] early in the chapter at a moment of character development or setting establishment
- Place [IMAGE_PLACEHOLDER_2] at a climactic or emotionally charged scene in the middle or later part
- DO NOT place both placeholders at the end of the chapter
- Create image prompts that are sophisticated and atmospheric for young adult readers
- Include nuanced details about lighting, mood, character expressions, and symbolism
- Focus on emotional depth and visual storytelling
- Consider the genre's visual conventions and themes
- Ensure placeholders enhance the dramatic tension and emotional impact";

            default:
                return "";
        }
    }

    private function getQuizInstructions(string $ageGroup): string
    {
        switch ($ageGroup) {
            case GenreStory::AGE_GROUP_7_10:
                return "Quiz Instructions for Age Group 7-10:
- Create 5 quiz questions based on the chapter content to test reading comprehension
- Questions should be age-appropriate and cover different aspects of the story
- Include multiple choice questions with 4 options each
- Ensure one option is clearly the correct answer
- Questions should test understanding of plot, characters, setting, and vocabulary";

            case GenreStory::AGE_GROUP_11_14:
                return "Quiz Instructions for Age Group 11-14:
- Create 5 quiz questions based on the chapter content to test reading comprehension
- Questions should be age-appropriate and cover different aspects of the story
- Include multiple choice questions with 4 options each
- Ensure one option is clearly the correct answer
- Questions should test understanding of plot, characters, setting, and vocabulary";

            case GenreStory::AGE_GROUP_15_18:
                return "Quiz Instructions for Age Group 15-18:
- Create 5 quiz questions based on the chapter content to test reading comprehension
- Questions should be age-appropriate and cover different aspects of the story
- Include multiple choice questions with 4 options each
- Ensure one option is clearly the correct answer
- Questions should test understanding of plot, characters, setting, and vocabulary";

            default:
                return "";
        }
    }

    private function getChapterPrompt(int $chapterNumber, string $ageGroup): string
    {
        switch ($chapterNumber) {
            case 1:
                return "Chapter 1 Goals:
- Introduce the main characters and setting
- Establish the initial situation and conflict
- Create an engaging opening that hooks the reader
- Set up the story's tone and atmosphere
- Introduce the main problem or challenge
- End with a compelling reason to continue reading";

            case 2:
                return "Chapter 2 Goals:
- Develop the conflict and raise the stakes
- Show character reactions to the initial problem
- Introduce obstacles or complications
- Build tension and suspense
- Develop character relationships
- End with a new challenge or revelation";

            case 3:
                return "Chapter 3 Goals:
- Escalate the conflict to its peak
- Show characters facing their biggest challenges
- Include a major turning point or crisis
- Reveal important information or secrets
- Create maximum tension and suspense
- End with a cliffhanger or major decision point";

            case 4:
                return "Chapter 4 Goals:
- Begin the resolution process
- Show characters working toward solutions
- Include moments of hope and progress
- Resolve some subplots or minor conflicts
- Build toward the final climax
- End with anticipation for the conclusion";

            case 5:
                return "Chapter 5 Goals:
- Provide a satisfying resolution to the main conflict
- Show character growth and learning
- Tie up loose ends and subplots
- Create a memorable and meaningful ending
- Leave readers with a positive message or lesson
- End with closure and satisfaction";

            default:
                return "Chapter {$chapterNumber} Goals:
- Continue the story progression naturally
- Maintain consistency with previous chapters
- Build toward the overall story resolution
- Keep readers engaged and interested";
        }
    }

    private function getAgeGroupRequirements(string $ageGroup): string
    {
        switch ($ageGroup) {
            case GenreStory::AGE_GROUP_7_10:
                return "Age Group 7-10 Requirements:
- Use simple, clear sentences (5-15 words per sentence)
- Include basic vocabulary appropriate for early readers
- Focus on concrete, visual descriptions
- Include plenty of dialogue and action
- Use repetition and rhythm in language
- Keep paragraphs short (2-3 sentences)
- Include humor and positive themes
- Avoid complex subplots or abstract concepts
- Target word count per chapter: 200-400 words
- Reading level: Early chapter book";

            case GenreStory::AGE_GROUP_11_14:
                return "Age Group 11-14 Requirements:
- Use varied sentence structures (simple to complex)
- Include intermediate vocabulary with context clues
- Balance description with action and dialogue
- Include character development and emotions
- Use figurative language (similes, metaphors)
- Include some suspense and mystery elements
- Address themes of friendship, identity, and growth
- Target word count per chapter: 400-600 words
- Reading level: Middle grade";

            case GenreStory::AGE_GROUP_15_18:
                return "Age Group 15-18 Requirements:
- Use sophisticated sentence structures and vocabulary
- Include deeper character development and internal conflicts
- Explore complex themes and moral dilemmas
- Use advanced literary techniques
- Include nuanced dialogue and relationships
- Address relevant social and personal issues
- Include more mature themes while remaining appropriate
- Target word count per chapter: 600-800 words
- Reading level: Young adult";

            default:
                return "";
        }
    }

    private function saveChapterToDatabase(GenrePlot $plot, string $ageGroup, int $chapterNumber, array $chapterData): GenreStory
    {
        $story = new GenreStory();
        $story->setPlot($plot);
        $story->setAgeGroup($ageGroup);
        $story->setChapterNumber($chapterNumber);
        $story->setContent($chapterData['content']);
        $story->setSummary($chapterData['summary']);
        $story->setAccumulativeSummary($chapterData['accumulativeSummary']);
        $story->setWordCount($chapterData['wordCount']);
        $story->setReadingTime($chapterData['readingTime']);
        $story->setVocabulary($chapterData['vocabulary']);

        // Store image prompts in vocabulary field if they exist
        if (isset($chapterData['imagePrompts']) && !empty($chapterData['imagePrompts'])) {
            $currentVocabulary = $story->getVocabulary() ?? [];
            $currentVocabulary['image_prompts'] = $chapterData['imagePrompts'];
            $story->setVocabulary($currentVocabulary);
        }

        // Store quiz data if it exists
        if (isset($chapterData['quiz']) && !empty($chapterData['quiz'])) {
            $story->setQuiz($chapterData['quiz']);
        }

        $this->entityManager->persist($story);
        $this->entityManager->flush();

        return $story;
    }
} 