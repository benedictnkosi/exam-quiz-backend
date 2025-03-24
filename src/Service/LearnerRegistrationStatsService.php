<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerRegistrationStatsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
    }

    public function getDailyRegistrationStats(): array
    {
        try {
            $this->logger->info("Getting daily learner registration statistics for the past 30 days");

            // Calculate date range
            $endDate = new \DateTime();
            $startDate = (new \DateTime())->modify('-30 days');

            $qb = $this->em->createQueryBuilder();
            $qb->select('SUBSTRING(l.created, 1, 10) as date, COUNT(l.id) as count')
                ->from('App\\Entity\\Learner', 'l')
                ->where('l.created >= :startDate')
                ->andWhere('l.created <= :endDate')
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
            $this->logger->error('Error getting daily registration stats: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving daily registration statistics: ' . $e->getMessage()
            ];
        }
    }

    public function getTotalLearners(): array
    {
        try {
            $this->logger->info("Getting total number of learners");

            $qb = $this->em->createQueryBuilder();
            $qb->select('COUNT(l.id) as total')
                ->from('App\\Entity\\Learner', 'l');

            $result = $qb->getQuery()->getSingleResult();

            return [
                'status' => 'OK',
                'data' => [
                    'total' => (int) $result['total']
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting total learners: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving total number of learners: ' . $e->getMessage()
            ];
        }
    }
}