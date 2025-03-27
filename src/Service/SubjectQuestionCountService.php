<?php

namespace App\Service;

use App\Entity\Subject;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class SubjectQuestionCountService
{
    private const REQUIRED_QUESTIONS_PER_SUBJECT = 100;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get the number of questions for each subject in a particular term
     *
     * @param int $term The term number
     * @return array Array of subjects with their question counts and remaining questions needed
     */
    public function getQuestionCountsByTerm(int $term): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s.name', 'g.number as gradeNumber', 'COUNT(q.id) as questionCount', 'l.name as capturerName')
            ->from(Subject::class, 's')
            ->leftJoin('s.grade', 'g')
            ->leftJoin('s.capturer', 'l')
            ->leftJoin(Question::class, 'q', 'WITH', 'q.subject = s AND q.term = :term')
            ->where('s.active = :active')
            ->setParameter('term', $term)
            ->setParameter('active', true)
            ->groupBy('s.id')
            ->orderBy('g.number', 'DESC')
            ->addOrderBy('questionCount', 'ASC');

        $results = $qb->getQuery()->getResult();

        $totalRemaining = 0;
        $remainingByGrade = [];
        $subjects = array_map(function ($result) use (&$totalRemaining, &$remainingByGrade) {
            $currentCount = (int) $result['questionCount'];

            // Skip if already has more than required questions
            if ($currentCount >= self::REQUIRED_QUESTIONS_PER_SUBJECT) {
                return [
                    'subject_name' => $result['name'],
                    'grade' => 'Grade ' . $result['gradeNumber'],
                    'current_question_count' => $currentCount,
                    'remaining_questions_needed' => 0,
                    'capturer' => $result['capturerName'] ?? null,
                    'subject_id' => $result['subjectId'] ?? null
                ];
            }

            $remaining = self::REQUIRED_QUESTIONS_PER_SUBJECT - $currentCount;
            $totalRemaining += $remaining;

            // Track remaining questions by grade
            $gradeNumber = $result['gradeNumber'];
            if (!isset($remainingByGrade[$gradeNumber])) {
                $remainingByGrade[$gradeNumber] = 0;
            }
            $remainingByGrade[$gradeNumber] += $remaining;

            return [
                'subject_name' => $result['name'],
                'grade' => 'Grade ' . $gradeNumber,
                'current_question_count' => $currentCount,
                'remaining_questions_needed' => $remaining,
                'capturer' => $result['capturerName'] ?? null,
                'subject_id' => $result['subjectId'] ?? null
            ];
        }, $results);

        // Format remaining questions by grade with specific keys
        $formattedRemainingByGrade = [];
        foreach ($remainingByGrade as $gradeNumber => $remaining) {
            $formattedRemainingByGrade["total_remaining_questions_needed_grade{$gradeNumber}"] = $remaining;
        }

        return [
            'subjects' => $subjects,
            'total_remaining_questions_needed' => $totalRemaining,
            ...$formattedRemainingByGrade
        ];
    }
}