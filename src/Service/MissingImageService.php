<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MissingImageService
{
    private string $imageBasePath;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        $this->imageBasePath = __DIR__ . '/../../public/assets/images/learnMzansi/';
    }

    public function checkMissingImages(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        try {
            $questions = $this->entityManager->getRepository(Question::class)->findAll();
            $missingImages = [];

            foreach ($questions as $question) {
                $missingImagesForQuestion = $this->checkQuestionImages($question);
                if (!empty($missingImagesForQuestion)) {
                    $missingImages[] = [
                        'question_id' => $question->getId(),
                        'subject' => $question->getSubject()?->getName(),
                        'missing_images' => $missingImagesForQuestion
                    ];
                }
            }

            return [
                'status' => 'OK',
                'total_questions_checked' => count($questions),
                'questions_with_missing_images' => count($missingImages),
                'missing_images' => $missingImages
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error checking for missing images: ' . $e->getMessage()
            ];
        }
    }

    private function checkQuestionImages(Question $question): array
    {
        $missingImages = [];

        // Check context image
        $contextImagePath = $question->getImagePath();
        if ($contextImagePath && !empty($contextImagePath) && !str_contains($contextImagePath, 'NULL') && !file_exists($this->imageBasePath . $contextImagePath)) {
            $missingImages[] = [
                'type' => 'context',
                'path' => $contextImagePath
            ];
        }

        // Check question image
        $questionImagePath = $question->getQuestionImagePath();
        if ($questionImagePath && !empty($questionImagePath) && !str_contains($questionImagePath, 'NULL') && !file_exists($this->imageBasePath . $questionImagePath)) {
            $missingImages[] = [
                'type' => 'question',
                'path' => $questionImagePath
            ];
        }

        // Check answer image
        $answerImagePath = $question->getAnswerImage();
        if ($answerImagePath && !empty($answerImagePath) && !str_contains($answerImagePath, 'NULL') && !file_exists($this->imageBasePath . $answerImagePath)) {
            $missingImages[] = [
                'type' => 'answer',
                'path' => $answerImagePath
            ];
        }

        return $missingImages;
    }
}