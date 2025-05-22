<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class QuestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function updatePracticeStatus(int $questionId, string $status, string $uid): array
    {
        try {
            // Get the learner and check if they are an admin
            $learner = $this->entityManager->getRepository('App\Entity\Learner')->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            if ($learner->getRole() !== 'admin') {
                return [
                    'status' => 'NOK',
                    'message' => 'Only administrators can update practice status'
                ];
            }

            $question = $this->entityManager->getRepository(Question::class)->find($questionId);

            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            $question->setPracticeStatus($status);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Practice status updated successfully',
                'question_id' => $questionId,
                'practice_status' => $status
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'NOK',
                'message' => 'Failed to update practice status: ' . $e->getMessage()
            ];
        }
    }
}