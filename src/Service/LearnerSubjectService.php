<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Learnersubjects;
use App\Entity\Subject;
use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LearnerSubjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function addAllSubjectsForGrade(string $uid, string $gradeNumber): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Find the grade
            $grade = $this->entityManager->getRepository(Grade::class)->findOneBy(['number' => $gradeNumber]);
            if (!$grade) {
                return [
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                ];
            }

            // Get all active subjects for the grade
            $subjects = $this->entityManager->getRepository(Subject::class)->findBy([
                'grade' => $grade,
                'active' => true
            ]);

            if (empty($subjects)) {
                return [
                    'status' => 'NOK',
                    'message' => 'No active subjects found for this grade'
                ];
            }

            $addedSubjects = 0;
            $skippedSubjects = 0;

            foreach ($subjects as $subject) {
                // Check if learner already has this subject
                $existingLearnerSubject = $this->entityManager->getRepository(Learnersubjects::class)->findOneBy([
                    'learner' => $learner,
                    'subject' => $subject
                ]);

                if (!$existingLearnerSubject) {
                    // Create new learner subject
                    $learnerSubject = new Learnersubjects();
                    $learnerSubject->setLearner($learner);
                    $learnerSubject->setSubject($subject);
                    $learnerSubject->setLastUpdated(new \DateTime());
                    $learnerSubject->setPercentage(0);

                    $this->entityManager->persist($learnerSubject);
                    $addedSubjects++;
                } else {
                    $skippedSubjects++;
                }
            }

            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => sprintf(
                    'Successfully processed subjects. Added: %d, Already existed: %d',
                    $addedSubjects,
                    $skippedSubjects
                ),
                'added_count' => $addedSubjects,
                'skipped_count' => $skippedSubjects,
                'total_subjects' => count($subjects)
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error adding subjects: ' . $e->getMessage()
            ];
        }
    }
} 