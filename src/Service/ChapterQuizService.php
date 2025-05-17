<?php

namespace App\Service;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ChapterQuizService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getChapterQuiz(int $chapterId): array
    {
        try {
            // Find the chapter
            $chapter = $this->entityManager->getRepository(Book::class)
                ->find($chapterId);

            if (!$chapter) {
                return [
                    'status' => 'NOK',
                    'message' => 'Chapter not found'
                ];
            }

            $quiz = $chapter->getQuiz();
            if (!$quiz) {
                return [
                    'status' => 'NOK',
                    'message' => 'No quiz available for this chapter'
                ];
            }

            return [
                'status' => 'OK',
                'data' => [
                    'chapterId' => $chapter->getId(),
                    'chapterName' => $chapter->getChapterName(),
                    'quiz' => $quiz
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting chapter quiz: ' . $e->getMessage()
            ];
        }
    }
}