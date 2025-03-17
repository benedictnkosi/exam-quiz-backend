<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
use App\Entity\Favorites;
use App\Entity\SubjectPoints;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TestDataCleanupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function cleanupTestData(): array
    {
        try {
            // Find all test learners
            $testLearners = $this->entityManager->getRepository(Learner::class)
                ->createQueryBuilder('l')
                ->where('l.name LIKE :pattern')
                ->setParameter('pattern', '%test%')
                ->getQuery()
                ->getResult();

            if (empty($testLearners)) {
                return [
                    'status' => 'OK',
                    'message' => 'No test learners found to delete',
                    'deleted_count' => 0
                ];
            }

            $learnerIds = array_map(fn($learner) => $learner->getId(), $testLearners);

            // Delete subject points for test learners
            $subjectPointsDeleted = $this->entityManager->createQueryBuilder()
                ->delete(SubjectPoints::class, 'sp')
                ->where('sp.learner IN (:learnerIds)')
                ->setParameter('learnerIds', $learnerIds)
                ->getQuery()
                ->execute();

            // Delete favorites for test learners
            $favoritesDeleted = $this->entityManager->createQueryBuilder()
                ->delete(Favorites::class, 'f')
                ->where('f.learner IN (:learnerIds)')
                ->setParameter('learnerIds', $learnerIds)
                ->getQuery()
                ->execute();

            // Delete results for test learners
            $resultsDeleted = $this->entityManager->createQueryBuilder()
                ->delete(Result::class, 'r')
                ->where('r.learner IN (:learnerIds)')
                ->setParameter('learnerIds', $learnerIds)
                ->getQuery()
                ->execute();

            // Delete the test learners
            $learnersDeleted = count($testLearners);
            foreach ($testLearners as $learner) {
                $this->entityManager->remove($learner);
            }

            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Test data cleanup completed successfully',
                'deleted_count' => [
                    'learners' => $learnersDeleted,
                    'favorites' => $favoritesDeleted,
                    'results' => $resultsDeleted,
                    'subject_points' => $subjectPointsDeleted
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error in cleanupTestData: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error cleaning up test data: ' . $e->getMessage()
            ];
        }
    }
}