<?php

namespace App\Service;

use App\Entity\StoryArc;
use App\Entity\ReadingLevel;
use App\Entity\Book;
use App\Repository\StoryArcRepository;
use App\Repository\ReadingLevelRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;

class ChapterGeneratorService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StoryArcRepository $storyArcRepository,
        private readonly ReadingLevelRepository $readingLevelRepository,
        private readonly BookRepository $bookRepository,
        private readonly OpenAIService $openAIService
    ) {
    }

    public function generateChaptersForNewArcs(): array
    {
        $newArcs = $this->storyArcRepository->findBy(['status' => StoryArc::STATUS_NEW]);
        $readingLevels = $this->readingLevelRepository->findBy(['level' => [1, 2]]);
        $generatedChapters = [];

        error_log("Starting chapter generation for " . count($newArcs) . " new story arcs");

        foreach ($newArcs as $arc) {
            error_log("Processing story arc: " . $arc->getChapterName());

            // Generate the base chapter at level 2
            $baseLevel = $readingLevels[0]; // Assuming level 2 is the first level
            error_log("Generating base chapter for reading level: " . $baseLevel->getName());
            $baseChapter = $this->generateChapter($arc, $baseLevel);
            $generatedChapters[] = $baseChapter;

            // Generate versions for other reading levels
            for ($i = 1; $i < count($readingLevels); $i++) {
                $targetLevel = $readingLevels[$i];
                error_log("Generating chapter for reading level: " . $targetLevel->getName());

                // Use the story arc's chapter number
                $chapterNumber = $arc->getChapterNumber();

                // Rewrite the chapter for the target reading level with retry logic
                $maxRetries = 3;
                $retryCount = 0;
                $rewrittenChapter = null;
                $lastError = null;

                while ($retryCount < $maxRetries) {
                    try {
                        $rewrittenChapter = $this->openAIService->rewriteChapterForReadingLevel(
                            $baseChapter['content'],
                            $targetLevel->getName(),
                            $targetLevel->getChapterWords()
                        );
                        break; // Success, exit the retry loop
                    } catch (\Exception $e) {
                        $lastError = $e;
                        $retryCount++;
                        if ($retryCount < $maxRetries) {
                            // Wait for 2 seconds before retrying (exponential backoff)
                            sleep(2 * $retryCount);
                        }
                    }
                }

                if (!$rewrittenChapter) {
                    throw new \Exception('Failed to generate reading level content after ' . $maxRetries . ' attempts. Last error: ' . $lastError->getMessage());
                }

                // Create and save the book chapter
                $book = new Book();
                $book->setStoryArc($arc);
                $book->setReadingLevel($targetLevel);
                $book->setChapterName($arc->getChapterName());
                $book->setChapterNumber($chapterNumber);
                $book->setQuiz(json_encode($baseChapter['quiz'])); // Reuse original quiz
                $book->setContent($rewrittenChapter['content']);
                $book->setSummary($baseChapter['summary']); // Reuse original summary
                $book->setWordCount(str_word_count($rewrittenChapter['content']));
                $book->setLevel($targetLevel->getLevel());
                $book->setStatus(Book::STATUS_ACTIVE);
                $publishDate = $arc->getPublishDate();
                if ($publishDate) {
                    $publishDate = new \DateTime($publishDate->format('Y-m-d'));
                    $publishDate->setTime(18, 0, 0);
                }
                $book->setPublishDate($publishDate);
                $book->setImage($arc->getImage());

                $this->bookRepository->save($book, true);

                $generatedChapters[] = [
                    'arc_id' => $arc->getId(),
                    'reading_level' => $targetLevel->getLevel(),
                    'chapter_name' => $arc->getChapterName(),
                    'chapter_number' => $chapterNumber,
                    'content' => $rewrittenChapter['content'],
                    'summary' => $baseChapter['summary'], // Reuse original summary
                    'quiz' => $baseChapter['quiz'], // Reuse original quiz
                    'word_count' => str_word_count($rewrittenChapter['content']),
                    'level' => $targetLevel->getLevel(),
                    'status' => Book::STATUS_ACTIVE,
                    'publish_date' => $arc->getPublishDate()?->format('Y-m-d H:i:s'),
                    'image' => $arc->getImage()
                ];
            }

            // Update arc status to in_progress
            $arc->setStatus(StoryArc::STATUS_IN_PROGRESS);
            error_log("Updated story arc status to IN_PROGRESS");
        }

        $this->entityManager->flush();
        error_log("Completed chapter generation. Generated " . count($generatedChapters) . " chapters total.");
        return $generatedChapters;
    }

    private function generateChapter(StoryArc $arc, ReadingLevel $level): array
    {
        // Use the story arc's chapter number
        $chapterNumber = $arc->getChapterNumber();

        // Get past 5 chapters
        $pastChapters = $this->bookRepository->findBy(
            ['storyArc' => $arc],
            ['chapterNumber' => 'DESC'],
            5
        );

        // Get the previous chapter's full content and summaries for others
        $previousChapterContent = null;
        $pastSummaries = [];

        foreach ($pastChapters as $index => $chapter) {
            if ($index === 0) {
                // For the most recent chapter, use full content
                $previousChapterContent = $chapter->getContent();
            } else {
                // For older chapters, use summaries
                $pastSummaries[] = [
                    'chapter_number' => $chapter->getChapterNumber(),
                    'summary' => $chapter->getSummary()
                ];
            }
        }

        // Get future plot information
        $futureArcs = $this->storyArcRepository->findBy(
            ['status' => StoryArc::STATUS_NEW],
            ['publishDate' => 'ASC'],
            3
        );
        $futurePlot = array_map(function ($futureArc) {
            return [
                'chapter_name' => $futureArc->getChapterName(),
                'goal' => $futureArc->getGoal()
            ];
        }, $futureArcs);

        // Character information
        $characterInfo = "Nelo is a curious 12-year-old boy from Johannesburg, South Africa, with a quiet strength and a big heart. He lives in a vibrant township where the sounds of kwaito music and the smell of braai often fill the air. He loves asking questions—even the ones adults don't want to answer—and keeps a small notebook where he writes down thoughts, dreams, and drawings. Though he's not the loudest in class, his friends rely on him for his honesty and calm presence. Nelo enjoys playing soccer in the dusty streets with his friends, listening to Amapiano music on his cheap but beloved headphones, and spending time with his grandmother, who always has a story or riddle to share. He doesn't know it yet, but this year will shape him in ways he never imagined.";

        // Generate chapter content using OpenAI with retry logic
        $maxRetries = 3;
        $retryCount = 0;
        $result = null;
        $lastError = null;

        while ($retryCount < $maxRetries) {
            try {
                $result = $this->openAIService->generateChapterContent(
                    $arc->getTheme(),
                    $arc->getGoal(),
                    $arc->getChapterName(),
                    $arc->getOutline(),
                    $level->getName(),
                    $characterInfo,
                    $level->getChapterWords(),
                    $pastSummaries,
                    $futurePlot,
                    $previousChapterContent
                );
                break; // Success, exit the retry loop
            } catch (\Exception $e) {
                $lastError = $e;
                $retryCount++;
                if ($retryCount < $maxRetries) {
                    // Wait for 2 seconds before retrying (exponential backoff)
                    sleep(2 * $retryCount);
                }
            }
        }

        if (!$result) {
            throw new \Exception('Failed to generate chapter content after ' . $maxRetries . ' attempts. Last error: ' . $lastError->getMessage());
        }

        $content = $result['content'];
        $summary = $result['summary'];
        $quiz = $result['quiz'];
        $wordCount = str_word_count($content);

        // Create and save the book chapter
        $book = new Book();
        $book->setStoryArc($arc);
        $book->setReadingLevel($level);
        $book->setChapterName($arc->getChapterName());
        $book->setChapterNumber($chapterNumber);
        $book->setQuiz(json_encode($quiz));
        $book->setContent($content);
        $book->setSummary($summary);
        $book->setWordCount($wordCount);
        $book->setLevel($level->getLevel());
        $book->setStatus(Book::STATUS_ACTIVE);
        $publishDate = $arc->getPublishDate();
        if ($publishDate) {
            $publishDate = new \DateTime($publishDate->format('Y-m-d'));
            $publishDate->setTime(18, 0, 0);
        }
        $book->setPublishDate($publishDate);
        $book->setImage($arc->getImage());

        $this->bookRepository->save($book, true);

        return [
            'arc_id' => $arc->getId(),
            'reading_level' => $level->getLevel(),
            'chapter_name' => $arc->getChapterName(),
            'chapter_number' => $chapterNumber,
            'content' => $content,
            'summary' => $summary,
            'quiz' => $quiz,
            'word_count' => $wordCount,
            'level' => $level->getLevel(),
            'status' => Book::STATUS_ACTIVE,
            'publish_date' => $arc->getPublishDate()?->format('Y-m-d H:i:s'),
            'image' => $arc->getImage()
        ];
    }

    private function generateSummary(string $content): string
    {
        // For now, just take the first 50 words as a summary
        $words = explode(' ', $content);
        $summaryWords = array_slice($words, 0, 50);
        return implode(' ', $summaryWords) . '...';
    }
}