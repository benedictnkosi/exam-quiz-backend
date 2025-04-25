<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class QuestionTopicUpdateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function updateQuestionTopic(int $questionId, string $topic): array
    {
        try {
            $question = $this->entityManager->getRepository(Question::class)->find($questionId);

            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            $question->setTopic($topic);
            $question->setUpdated(new \DateTime());

            $this->entityManager->persist($question);
            $this->entityManager->flush();

            $this->logger->info('Updated topic for question {id} to {topic}', [
                'id' => $questionId,
                'topic' => $topic
            ]);

            return [
                'status' => 'OK',
                'message' => 'Question topic updated successfully',
                'question_id' => $questionId,
                'topic' => $topic
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error updating question topic: {error}', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'NOK',
                'message' => 'Error updating question topic: ' . $e->getMessage()
            ];
        }
    }
}