<?php

namespace App\Service;

use App\Entity\ReportedMessages;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ReportedMessageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getReportedMessages(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('r')
                ->from(ReportedMessages::class, 'r')
                ->orderBy('r.createdAt', 'DESC');

            // Add filters if provided
            if (!empty($filters['author_id'])) {
                $qb->andWhere('r.author = :author')
                    ->setParameter('author', $filters['author_id']);
            }

            if (!empty($filters['reporter_id'])) {
                $qb->andWhere('r.reporter = :reporter')
                    ->setParameter('reporter', $filters['reporter_id']);
            }

            if (!empty($filters['message_uid'])) {
                $qb->andWhere('r.messageUid = :messageUid')
                    ->setParameter('messageUid', $filters['message_uid']);
            }

            // Add pagination
            $qb->setFirstResult($offset)
                ->setMaxResults($limit);

            $reports = $qb->getQuery()->getResult();

            // Format the response
            $formattedReports = [];
            foreach ($reports as $report) {
                $formattedReports[] = [
                    'id' => $report->getId(),
                    'created_at' => $report->getCreatedAt()->format('Y-m-d H:i:s'),
                    'author_id' => $report->getAuthor()->getUid(),
                    'author_name' => $report->getAuthor()->getName(),
                    'reporter_id' => $report->getReporter()->getUid(),
                    'reporter_name' => $report->getReporter()->getName(),
                    'message_uid' => $report->getMessageUid(),
                    'message' => $report->getMessage()
                ];
            }

            return [
                'status' => 'OK',
                'message' => 'Reports retrieved successfully',
                'reports' => $formattedReports,
                'total' => count($reports),
                'limit' => $limit,
                'offset' => $offset
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving reports: ' . $e->getMessage()
            ];
        }
    }

    public function deleteReportedMessage(int $reportId): array
    {
        try {
            $report = $this->entityManager->getRepository(ReportedMessages::class)->find($reportId);

            if (!$report) {
                return [
                    'status' => 'NOK',
                    'message' => 'Report not found'
                ];
            }

            $this->entityManager->remove($report);
            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Report deleted successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error deleting report: ' . $e->getMessage()
            ];
        }
    }
}