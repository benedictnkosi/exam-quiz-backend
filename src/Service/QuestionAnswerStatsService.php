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

            $sql = "
                SELECT 
                    DATE(r.created) as date,
                    COUNT(DISTINCT r.id) as count
                FROM result r
                JOIN learner l ON r.learner = l.id
                WHERE r.created >= :startDate
                AND r.created <= :endDate
                AND l.email NOT LIKE :testEmail
                GROUP BY DATE(r.created)
                ORDER BY date ASC
            ";

            $this->logger->info('Executing SQL query: ' . $sql);
            $this->logger->info('Parameters: ', [
                'startDate' => $startDate->format('Y-m-d H:i:s'),
                'endDate' => $endDate->format('Y-m-d H:i:s'),
                'testEmail' => '%test%'
            ]);

            $stmt = $this->em->getConnection()->prepare($sql);
            $result = $stmt->executeQuery([
                'startDate' => $startDate->format('Y-m-d H:i:s'),
                'endDate' => $endDate->format('Y-m-d H:i:s'),
                'testEmail' => '%test%'
            ]);

            $results = $result->fetchAllAssociative();
            $this->logger->info('Query results: ', $results);

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