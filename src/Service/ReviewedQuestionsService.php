<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ReviewedQuestionsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getReviewerStats(?string $fromDate): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('IDENTITY(q.reviewer) as reviewer_id', 'q.status', 'COUNT(q.id) as count', 'l.name as reviewer_name')
                ->from(Question::class, 'q')
                ->leftJoin(Learner::class, 'l', 'WITH', 'q.reviewer = l.id')
                ->where('q.reviewer IS NOT NULL')
                ->groupBy('q.reviewer', 'q.status', 'l.name');

            if ($fromDate) {
                try {
                    $date = new \DateTime($fromDate);
                    $qb->andWhere('q.reviewedAt >= :fromDate')
                        ->setParameter('fromDate', $date);
                } catch (\Exception $e) {
                    return [
                        'status' => 'NOK',
                        'message' => 'Invalid from_date format. Use ISO format (YYYY-MM-DD).'
                    ];
                }
            }

            $results = $qb->getQuery()->getResult();

            $reviewerStats = [];
            foreach ($results as $result) {
                $reviewerId = $result['reviewer_id'];
                $status = $result['status'] ?? 'new';
                $count = $result['count'] ?? 0;
                $reviewerName = $result['reviewer_name'] ?? 'Unknown';

                if (!isset($reviewerStats[$reviewerId])) {
                    $reviewerStats[$reviewerId] = [
                        'reviewer' => $reviewerId,
                        'reviewer_name' => $reviewerName,
                        'approved' => 0,
                        'rejected' => 0,
                        'new' => 0,
                        'total' => 0
                    ];
                }

                if ($status === 'approved') {
                    $reviewerStats[$reviewerId]['approved'] += $count;
                } elseif ($status === 'rejected') {
                    $reviewerStats[$reviewerId]['rejected'] += $count;
                } else {
                    $reviewerStats[$reviewerId]['new'] += $count;
                }

                $reviewerStats[$reviewerId]['total'] += $count;
            }

            return [
                'status' => 'OK',
                'data' => array_values($reviewerStats)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in getReviewerStats: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting reviewer statistics',
                'error' => $e->getMessage()
            ];
        }
    }
}