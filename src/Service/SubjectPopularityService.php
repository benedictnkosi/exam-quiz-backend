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

            // Calculate popularity metrics for each subject
            $subjectPopularity = [];
            $totalAnswers = 0;

            // First pass to calculate total answers across all subjects
            foreach ($results as $result) {
                $totalAnswers += (int) $result['total_answers'];
            }

            // Second pass to calculate percentages and format data
            foreach ($results as $result) {
                $subjectTotalAnswers = (int) $result['total_answers'];
                $percentageOfTotal = $totalAnswers > 0 ? ($subjectTotalAnswers / $totalAnswers) * 100 : 0;

                $subjectPopularity[] = [
                    'subject_id' => $result['subject_id'],
                    'subject_name' => $result['subject_name'],
                    'total_answers' => $subjectTotalAnswers,
                    'unique_learners' => (int) $result['unique_learners'],
                    'unique_questions' => (int) $result['unique_questions'],
                    'percentage_of_total' => round($percentageOfTotal, 2),
                    'popularity_rank' => count($subjectPopularity) + 1
                ];
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
}