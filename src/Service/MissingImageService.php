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
        if ($question->getImagePath() && !file_exists($this->imageBasePath . $question->getImagePath())) {
            $missingImages[] = [
                'type' => 'context',
                'path' => $question->getImagePath()
            ];
        }

        // Check question image
        if ($question->getQuestionImagePath() && !file_exists($this->imageBasePath . $question->getQuestionImagePath())) {
            $missingImages[] = [
                'type' => 'question',
                'path' => $question->getQuestionImagePath()
            ];
        }

        // Check answer image
        if ($question->getAnswerImage() && !file_exists($this->imageBasePath . $question->getAnswerImage())) {
            $missingImages[] = [
                'type' => 'answer',
                'path' => $question->getAnswerImage()
            ];
        }

        return $missingImages;
    }
}