<?php

namespace App\Service;

use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubjectDifficultyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get subject difficulty analysis based on correct vs incorrect answers
     * 
     * @param int $grade Grade ID to filter results
     * @return array Array of subjects with their difficulty metrics
     */
    public function getSubjectDifficulty(int $grade): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select([
                's.id as subject_id',
                's.name as subject_name',
                'COUNT(r.id) as total_answers',
                'SUM(CASE WHEN r.outcome = :correct THEN 1 ELSE 0 END) as correct_answers',
                'SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) as incorrect_answers'
            ])
                ->from(Result::class, 'r')
                ->join('r.question', 'q')
                ->join('q.subject', 's')
                ->join('r.learner', 'l')
                ->where('q.active = :active')
                ->andWhere('q.status = :status')
                ->andWhere('l.grade = :grade')
                ->groupBy('s.id', 's.name')
                ->having('COUNT(r.id) >= :min_attempts')
                ->orderBy('s.name', 'ASC')
                ->setParameter('correct', 'correct')
                ->setParameter('incorrect', 'incorrect')
                ->setParameter('active', true)
                ->setParameter('status', 'approved')
                ->setParameter('grade', $grade)
                ->setParameter('min_attempts', 10); // Minimum number of attempts to consider

            $results = $qb->getQuery()->getResult();

            // Calculate difficulty metrics for each subject
            $subjectDifficulty = [];
            foreach ($results as $result) {
                $totalAnswers = (int) $result['total_answers'];
                $correctAnswers = (int) $result['correct_answers'];
                $incorrectAnswers = (int) $result['incorrect_answers'];

                // Calculate success rate
                $successRate = $totalAnswers > 0 ? ($correctAnswers / $totalAnswers) * 100 : 0;

                // Calculate difficulty level based on success rate
                $difficultyLevel = $this->calculateDifficultyLevel($successRate);

                $subjectDifficulty[] = [
                    'subject_id' => $result['subject_id'],
                    'subject_name' => $result['subject_name'],
                    'total_answers' => $totalAnswers,
                    'correct_answers' => $correctAnswers,
                    'incorrect_answers' => $incorrectAnswers,
                    'success_rate' => round($successRate, 2),
                    'difficulty_level' => $difficultyLevel
                ];
            }

            return [
                'status' => 'OK',
                'grade' => $grade,
                'subjects' => $subjectDifficulty
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating subject difficulty: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error calculating subject difficulty'
            ];
        }
    }

    /**
     * Calculate difficulty level based on success rate
     * 
     * @param float $successRate Success rate percentage
     * @return string Difficulty level
     */
    private function calculateDifficultyLevel(float $successRate): string
    {
        if ($successRate >= 80) {
            return 'Easy';
        } elseif ($successRate >= 60) {
            return 'Moderate';
        } elseif ($successRate >= 40) {
            return 'Challenging';
        } else {
            return 'Difficult';
        }
    }
}