<?php

namespace App\Service;

use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubjectPopularityService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get subject popularity analysis by grade based on total answers
     * 
     * @param int $grade Grade ID to filter results
     * @return array Array of subjects with their popularity metrics
     */
    public function getSubjectPopularity(int $grade): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select([
                's.id as subject_id',
                's.name as subject_name',
                'COUNT(r.id) as total_answers',
                'COUNT(DISTINCT r.learner) as unique_learners',
                'COUNT(DISTINCT r.question) as unique_questions'
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
                ->orderBy('total_answers', 'DESC')
                ->setParameter('active', true)
                ->setParameter('status', 'approved')
                ->setParameter('grade', $grade)
                ->setParameter('min_attempts', 10); // Minimum number of attempts to consider

            $results = $qb->getQuery()->getResult();

            // Group subjects by name and combine their metrics
            $groupedSubjects = [];
            $totalAnswers = 0;

            // First pass to group subjects and calculate totals
            foreach ($results as $result) {
                $subjectName = $this->getBaseSubjectName($result['subject_name']);
                $subjectTotalAnswers = (int) $result['total_answers'];
                $totalAnswers += $subjectTotalAnswers;

                if (!isset($groupedSubjects[$subjectName])) {
                    $groupedSubjects[$subjectName] = [
                        'subject_ids' => [],
                        'subject_name' => $subjectName,
                        'total_answers' => 0,
                        'unique_learners' => [],
                        'unique_questions' => []
                    ];
                }

                $groupedSubjects[$subjectName]['subject_ids'][] = $result['subject_id'];
                $groupedSubjects[$subjectName]['total_answers'] += $subjectTotalAnswers;
            }

            // Second pass to get unique learners and questions for each group
            foreach ($groupedSubjects as $subjectName => &$group) {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select([
                    'COUNT(DISTINCT r.learner) as unique_learners',
                    'COUNT(DISTINCT r.question) as unique_questions'
                ])
                    ->from(Result::class, 'r')
                    ->join('r.question', 'q')
                    ->join('q.subject', 's')
                    ->join('r.learner', 'l')
                    ->where('s.id IN (:subjectIds)')
                    ->andWhere('q.active = :active')
                    ->andWhere('q.status = :status')
                    ->andWhere('l.grade = :grade')
                    ->setParameter('subjectIds', $group['subject_ids'])
                    ->setParameter('active', true)
                    ->setParameter('status', 'approved')
                    ->setParameter('grade', $grade);

                $metrics = $qb->getQuery()->getOneOrNullResult();

                if ($metrics) {
                    $group['unique_learners'] = (int) $metrics['unique_learners'];
                    $group['unique_questions'] = (int) $metrics['unique_questions'];
                }
            }

            // Format the final results
            $subjectPopularity = [];
            $rank = 1;
            foreach ($groupedSubjects as $group) {
                $percentageOfTotal = $totalAnswers > 0 ? ($group['total_answers'] / $totalAnswers) * 100 : 0;

                $subjectPopularity[] = [
                    'subject_ids' => $group['subject_ids'],
                    'subject_name' => $group['subject_name'],
                    'total_answers' => $group['total_answers'],
                    'unique_learners' => $group['unique_learners'],
                    'unique_questions' => $group['unique_questions'],
                    'percentage_of_total' => round($percentageOfTotal, 2),
                    'popularity_rank' => $rank++
                ];
            }

            // Sort by total answers in descending order
            usort($subjectPopularity, function ($a, $b) {
                return $b['total_answers'] - $a['total_answers'];
            });

            // Update ranks after sorting
            foreach ($subjectPopularity as $index => &$subject) {
                $subject['popularity_rank'] = $index + 1;
            }

            return [
                'status' => 'OK',
                'grade' => $grade,
                'total_answers_across_subjects' => $totalAnswers,
                'subjects' => $subjectPopularity
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating subject popularity: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error calculating subject popularity'
            ];
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