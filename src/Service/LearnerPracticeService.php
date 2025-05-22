<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerPractice;
use App\Repository\LearnerPracticeRepository;
use Doctrine\ORM\EntityManagerInterface;

class LearnerPracticeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LearnerPracticeRepository $learnerPracticeRepository
    ) {
    }

    public function updateProgress(string $uid, string $subjectName, array $progressData): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $practice = $this->learnerPracticeRepository->findByLearnerAndSubject($learner->getId(), $subjectName);

            if (!$practice) {
                $practice = new LearnerPractice();
                $practice->setLearner($learner);
                $practice->setSubjectName($subjectName);
            }

            // Merge new progress data with existing progress
            $currentProgress = $practice->getProgress();
            $mergedProgress = array_merge_recursive($currentProgress, $progressData);
            $practice->setProgress($mergedProgress);
            $practice->setLastSeen(new \DateTime());

            $this->learnerPracticeRepository->save($practice, true);

            return [
                'status' => 'OK',
                'message' => 'Practice progress updated successfully',
                'practice' => [
                    'subject_name' => $practice->getSubjectName(),
                    'progress' => $practice->getProgress(),
                    'last_seen' => $practice->getLastSeen()->format('Y-m-d H:i:s')
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'NOK',
                'message' => 'Failed to update practice progress: ' . $e->getMessage()
            ];
        }
    }

    public function getProgress(string $uid, string $subjectName): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $practice = $this->learnerPracticeRepository->findByLearnerAndSubject($learner->getId(), $subjectName);

            if (!$practice) {
                return [
                    'status' => 'OK',
                    'message' => 'No practice record found',
                    'practice' => [
                        'subject_name' => $subjectName,
                        'progress' => [],
                        'last_seen' => null
                    ]
                ];
            }

            return [
                'status' => 'OK',
                'message' => 'Practice progress retrieved successfully',
                'practice' => [
                    'subject_name' => $practice->getSubjectName(),
                    'progress' => $practice->getProgress(),
                    'last_seen' => $practice->getLastSeen()->format('Y-m-d H:i:s')
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'NOK',
                'message' => 'Failed to get practice progress: ' . $e->getMessage()
            ];
        }
    }

    public function getAllProgress(string $uid): array
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $practices = $this->learnerPracticeRepository->findBy(['learner' => $learner]);

            $result = [];
            foreach ($practices as $practice) {
                $result[] = [
                    'subject_name' => $practice->getSubjectName(),
                    'progress' => $practice->getProgress(),
                    'last_seen' => $practice->getLastSeen()->format('Y-m-d H:i:s')
                ];
            }

            return [
                'status' => 'OK',
                'message' => 'All practice progress retrieved successfully',
                'practices' => $result
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'NOK',
                'message' => 'Failed to get all practice progress: ' . $e->getMessage()
            ];
        }
    }
}