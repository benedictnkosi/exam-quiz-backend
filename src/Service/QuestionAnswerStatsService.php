<?php

namespace App\Service;

use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class QuestionAnswerStatsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
    }

    public function getDailyAnswerStats(): array
    {
        try {
            $this->logger->info("Getting daily answer statistics for the past 30 days");

            // Calculate date range
            $endDate = new \DateTime();
            $startDate = (new \DateTime())->modify('-30 days');

            $qb = $this->em->createQueryBuilder();
            $qb->select('SUBSTRING(r.created, 1, 10) as date, COUNT(r.id) as count')
                ->from('App\\Entity\\Result', 'r')
                ->where('r.created >= :startDate')
                ->andWhere('r.created <= :endDate')
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);

            $results = $qb->getQuery()->getResult();

            // Format the results
            $stats = [];
            foreach ($results as $result) {
                $stats[] = [
                    'date' => $result['date'],
                    'count' => (int) $result['count']
                ];
            }

            return [
                'status' => 'OK',
                'data' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting daily answer stats: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving daily answer statistics: ' . $e->getMessage()
            ];
        }
    }
}