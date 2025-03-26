<?php

namespace App\Service;

use App\Entity\Subject;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class SubjectQuestionCountService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get the number of questions for each subject in a particular term and grade
     *
     * @param int $term The term number
     * @param int $gradeNumber The grade number
     * @return array Array of subjects with their question counts
     */
    public function getQuestionCountsByTerm(int $term): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s.name', 's.id as subjectId', 'g.number as gradeNumber', 'COUNT(q.id) as questionCount', 'l.name as capturerName')
            ->from(Subject::class, 's')
            ->leftJoin('s.grade', 'g')
            ->leftJoin('s.capturer', 'l')
            ->leftJoin(Question::class, 'q', 'WITH', 'q.subject = s AND q.term = :term')
            ->where('s.active = :active')
            ->setParameter('term', $term)
            ->setParameter('active', true)
            ->groupBy('s.id')
            ->orderBy('questionCount', 'DESC');

        $results = $qb->getQuery()->getResult();

        return array_map(function ($result) {
            return [
                'subject_name' => $result['name'],
                'grade' => 'Grade ' . $result['gradeNumber'],
                'question_count' => (int) $result['questionCount'],
                'capturer' => $result['capturerName'] ?? null,
                'subject_id' => $result['subjectId'] ?? null
            ];
        }, $results);
    }
}