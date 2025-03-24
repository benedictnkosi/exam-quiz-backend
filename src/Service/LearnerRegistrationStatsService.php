<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
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
            //where email does not contain test 

            $qb->select('SUBSTRING(l.created, 1, 10) as date, COUNT(l.id) as count')
                ->from('App\\Entity\\Learner', 'l')
                ->where('l.created >= :startDate')
                ->andWhere('l.created <= :endDate')
                ->andWhere('l.email NOT LIKE :testEmail')
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->setParameter('testEmail', '%test%');

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

    public function getUniqueLearnersAnsweredToday(): array
    {
        try {
            $this->logger->info("Getting count of unique learners who answered questions today");

            // Get today's date range
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            $tomorrow = (new \DateTime())->setTime(0, 0, 0)->modify('+1 day');

            $qb = $this->em->createQueryBuilder();
            $qb->select('COUNT(DISTINCT r.learner) as unique_learners')
                ->from('App\\Entity\\Result', 'r')
                ->where('r.created >= :today')
                ->andWhere('r.created < :tomorrow')
                ->setParameter('today', $today)
                ->setParameter('tomorrow', $tomorrow);

            $result = $qb->getQuery()->getSingleResult();

            return [
                'status' => 'OK',
                'data' => [
                    'unique_learners' => (int) $result['unique_learners'],
                    'date' => $today->format('Y-m-d')
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting unique learners answered today: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving unique learners count: ' . $e->getMessage()
            ];
        }
    }

    public function getAverageLearnersPerDay(): array
    {
        try {
            $this->logger->info("Getting average number of learners who answered questions per day for the past 30 days");

            // Calculate date range
            $endDate = new \DateTime();
            $endDate->setTime(23, 59, 59);
            $startDate = (new \DateTime())->modify('-30 days');
            $startDate->setTime(0, 0, 0);

            $qb = $this->em->createQueryBuilder();
            $qb->select('SUBSTRING(r.created, 1, 10) as date, COUNT(DISTINCT r.learner) as unique_learners')
                ->from('App\\Entity\\Result', 'r')
                ->where('r.created >= :startDate')
                ->andWhere('r.created <= :endDate')
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);

            $results = $qb->getQuery()->getResult();

            // Calculate average
            $totalLearners = 0;
            $daysWithActivity = count($results);
            $dailyStats = [];

            foreach ($results as $result) {
                $totalLearners += (int) $result['unique_learners'];
                $dailyStats[] = [
                    'date' => $result['date'],
                    'learners' => (int) $result['unique_learners']
                ];
            }

            $average = $daysWithActivity > 0 ? round($totalLearners / $daysWithActivity, 2) : 0;

            return [
                'status' => 'OK',
                'data' => [
                    'average_learners_per_day' => $average,
                    'total_days_with_activity' => $daysWithActivity,
                    'total_unique_learners' => $totalLearners,
                    'daily_breakdown' => $dailyStats,
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ]
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error getting average learners per day: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving average learners per day: ' . $e->getMessage()
            ];
        }
    }
}