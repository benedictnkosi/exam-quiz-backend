<?php

namespace App\Service;

use App\Entity\SubjectTopic;
use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubjectTopicService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function createTopicsForSubject(string $subjectName, array $topics, int $gradeNumber): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        try {
            // Find the grade
            $grade = $this->entityManager->getRepository(Grade::class)
                ->findOneBy(['number' => $gradeNumber]);

            if (!$grade) {
                return [
                    'status' => 'NOK',
                    'message' => "Grade with number '{$gradeNumber}' not found"
                ];
            }

            $createdTopics = [];
            $errors = [];

            foreach ($topics as $topicName) {
                try {
                    // Check if topic already exists for this subject and grade
                    $existingTopic = $this->entityManager->getRepository(SubjectTopic::class)
                        ->findOneBy([
                            'name' => $topicName,
                            'subjectName' => $subjectName,
                            'grade' => $grade
                        ]);

                    if ($existingTopic) {
                        $errors[] = "Topic '{$topicName}' already exists for subject '{$subjectName}' in grade {$gradeNumber}";
                        continue;
                    }

                    // Create new topic
                    $topic = new SubjectTopic();
                    $topic->setName($topicName);
                    $topic->setSubjectName($subjectName);
                    $topic->setGrade($grade);

                    $this->entityManager->persist($topic);
                    $createdTopics[] = $topicName;
                } catch (\Exception $e) {
                    $errors[] = "Error creating topic '{$topicName}': " . $e->getMessage();
                }
            }

            $this->entityManager->flush();

            return [
                'status' => 'OK',
                'message' => 'Topics created successfully',
                'created_topics' => $createdTopics,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error creating topics: ' . $e->getMessage()
            ];
        }
    }
}