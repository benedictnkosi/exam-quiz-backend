<?php

namespace App\Service;

use App\Entity\Subject;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubjectCapturerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Reset capturer to NULL for subjects with no questions in the past 48 hours
     */
    public function resetInactiveCapturers(): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();

            // Get subjects with capturers
            $qb->select('s')
                ->from(Subject::class, 's')
                ->where('s.capturer IS NOT NULL');

            $subjects = $qb->getQuery()->getResult();
            $resetCount = 0;

            foreach ($subjects as $subject) {
                // Check if there are any questions created in the past 48 hours
                $questionQb = $this->entityManager->createQueryBuilder();
                $questionQb->select('COUNT(q.id)')
                    ->from(Question::class, 'q')
                    ->where('q.subject = :subject')
                    ->andWhere('q.created >= :cutoff')
                    ->setParameter('subject', $subject)
                    ->setParameter('cutoff', new \DateTime('-48 hours'));

                $questionCount = $questionQb->getQuery()->getSingleScalarResult();

                if ($questionCount == 0) {
                    $subject->setCapturer(null);
                    $this->entityManager->persist($subject);
                    $resetCount++;
                }
            }

            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => "Successfully reset $resetCount inactive capturers",
                'reset_count' => $resetCount
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error resetting inactive capturers: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error resetting inactive capturers: ' . $e->getMessage()
            ];
        }
    }
}