<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

class QuestionCountService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get the number of questions for each year and term for a specific subject
     *
     * @param int $subjectId The subject ID
     * @return array Array of question counts grouped by year and term
     */
    public function getQuestionCountsByYearAndTerm(int $subjectId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('q.year', 'q.term', 'COUNT(q.id) as count')
            ->from(Question::class, 'q')
            ->where('q.subject = :subjectId')
            ->andWhere('q.active = :active')
            ->setParameter('subjectId', $subjectId)
            ->setParameter('active', true)
            ->groupBy('q.year', 'q.term')
            ->orderBy('q.year', 'DESC')
            ->addOrderBy('q.term', 'DESC');

        $results = $qb->getQuery()->getResult();

        // Format the results
        $formattedResults = [];
        foreach ($results as $result) {
            $formattedResults[] = [
                'year' => $result['year'],
                'term' => $result['term'],
                'count' => (int) $result['count']
            ];
        }

        return $formattedResults;
    }
}