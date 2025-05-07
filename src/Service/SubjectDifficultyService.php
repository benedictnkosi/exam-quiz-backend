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

            // Group subjects by name and combine their metrics
            $groupedSubjects = [];

            // First pass to group subjects and calculate totals
            foreach ($results as $result) {
                $subjectName = $this->getBaseSubjectName($result['subject_name']);
                $totalAnswers = (int) $result['total_answers'];
                $correctAnswers = (int) $result['correct_answers'];
                $incorrectAnswers = (int) $result['incorrect_answers'];

                if (!isset($groupedSubjects[$subjectName])) {
                    $groupedSubjects[$subjectName] = [
                        'subject_ids' => [],
                        'subject_name' => $subjectName,
                        'total_answers' => 0,
                        'correct_answers' => 0,
                        'incorrect_answers' => 0
                    ];
                }

                $groupedSubjects[$subjectName]['subject_ids'][] = $result['subject_id'];
                $groupedSubjects[$subjectName]['total_answers'] += $totalAnswers;
                $groupedSubjects[$subjectName]['correct_answers'] += $correctAnswers;
                $groupedSubjects[$subjectName]['incorrect_answers'] += $incorrectAnswers;
            }

            // Calculate difficulty metrics for each group
            $subjectDifficulty = [];
            foreach ($groupedSubjects as $group) {
                $totalAnswers = $group['total_answers'];
                $correctAnswers = $group['correct_answers'];
                $incorrectAnswers = $group['incorrect_answers'];

                // Calculate success rate
                $successRate = $totalAnswers > 0 ? ($correctAnswers / $totalAnswers) * 100 : 0;

                // Calculate difficulty level based on success rate
                $difficultyLevel = $this->calculateDifficultyLevel($successRate);

                $subjectDifficulty[] = [
                    'subject_ids' => $group['subject_ids'],
                    'subject_name' => $group['subject_name'],
                    'total_answers' => $totalAnswers,
                    'correct_answers' => $correctAnswers,
                    'incorrect_answers' => $incorrectAnswers,
                    'success_rate' => round($successRate, 2),
                    'difficulty_level' => $difficultyLevel
                ];
            }

            // Sort by success rate in ascending order (harder subjects first)
            usort($subjectDifficulty, function ($a, $b) {
                return $a['success_rate'] - $b['success_rate'];
            });

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

    /**
     * Extract the base subject name by removing paper numbers and other suffixes
     * 
     * @param string $subjectName Full subject name
     * @return string Base subject name
     */
    private function getBaseSubjectName(string $subjectName): string
    {
        // Remove paper numbers (P1, P2, etc.)
        $baseName = preg_replace('/\s*P\d+\s*$/', '', $subjectName);

        // Remove other common suffixes
        $baseName = preg_replace('/\s*\([^)]*\)\s*$/', '', $baseName);

        return trim($baseName);
    }
}