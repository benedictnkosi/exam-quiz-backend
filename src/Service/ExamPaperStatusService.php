<?php

namespace App\Service;

use App\Entity\ExamPaper;
use App\Repository\ExamPaperRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExamPaperStatusService
{
    public function __construct(
        private ExamPaperRepository $examPaperRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function updateStatus(int $examPaperId, string $status): ExamPaper
    {
        $examPaper = $this->examPaperRepository->find($examPaperId);
        if (!$examPaper) {
            throw new \InvalidArgumentException('Exam paper not found');
        }

        // Validate status
        $validStatuses = ['pending', 'processed_numbers', 'in_progress', 'done', 'error'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
        }

        $this->logger->info("Updating exam paper {$examPaperId} status to {$status}");

        $examPaper->setStatus($status);
        $this->entityManager->persist($examPaper);
        $this->entityManager->flush();

        return $examPaper;
    }

    /**
     * Get all exam papers with optional filtering
     * 
     * @param array $filters Optional filters to apply:
     *                      - status: Filter by status
     *                      - grade: Filter by grade
     *                      - year: Filter by year
     *                      - term: Filter by term
     *                      - subjectName: Filter by subject name
     * @param array $orderBy Optional ordering parameters:
     *                      - field: Field to order by (default: 'created')
     *                      - direction: Order direction ('ASC' or 'DESC', default: 'DESC')
     * @param int|null $limit Optional limit for number of results
     * @param int|null $offset Optional offset for pagination
     * @return array Array of exam papers
     */
    public function getAllExamPapers(
        array $filters = [],
        array $orderBy = ['field' => 'created', 'direction' => 'DESC'],
        ?int $limit = 20,
        ?int $offset = null
    ): array {
        $this->logger->info('Getting all exam papers with filters: ' . json_encode($filters));

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ep')
            ->from(ExamPaper::class, 'ep');

        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['status'])) {
                $qb->andWhere('ep.status = :status')
                    ->setParameter('status', $filters['status']);
            }
            if (isset($filters['grade'])) {
                $qb->andWhere('ep.grade = :grade')
                    ->setParameter('grade', $filters['grade']);
            }
            if (isset($filters['year'])) {
                $qb->andWhere('ep.year = :year')
                    ->setParameter('year', $filters['year']);
            }
            if (isset($filters['term'])) {
                $qb->andWhere('ep.term = :term')
                    ->setParameter('term', $filters['term']);
            }
            if (isset($filters['subjectName'])) {
                $qb->andWhere('ep.subjectName = :subjectName')
                    ->setParameter('subjectName', $filters['subjectName']);
            }
        }

        // Apply ordering
        $field = $orderBy['field'] ?? 'created';
        $direction = strtoupper($orderBy['direction'] ?? 'DESC');
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'DESC';
        }
        $qb->orderBy('ep.' . $field, $direction);

        // Apply pagination
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }
}