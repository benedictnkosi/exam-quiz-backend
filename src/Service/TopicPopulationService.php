<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Subject;
use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;

class TopicPopulationService
{
    private EntityManagerInterface $entityManager;
    private OpenAIService $openAIService;

    public function __construct(EntityManagerInterface $entityManager, OpenAIService $openAIService)
    {
        $this->entityManager = $entityManager;
        $this->openAIService = $openAIService;
    }

    public function updateTopicLecture(Topic $topic): bool
    {
        try {
            $lecture = $this->openAIService->generateLecture(
                $topic->getSubject()->getName() ?? 'Unknown Subject',
                $topic->getName(),
                $topic->getSubTopic()
            );

            $topic->setLecture($lecture);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            error_log('Failed to generate lecture: ' . $e->getMessage());
            return false;
        }
    }

    public function processSingleTopic(string $subTopic, Subject $subject): ?Topic
    {
        $subjectTopics = $subject->getTopics() ?? [];

        // Find the main topic for this subtopic
        $mainTopic = $this->findMainTopicForSubTopic($subTopic, $subjectTopics);

        if (!$mainTopic) {
            return null;
        }

        // Check if topic already exists
        $existingTopic = $this->entityManager->getRepository(Topic::class)->findOneBy([
            'name' => $mainTopic,
            'subTopic' => $subTopic,
            'subject' => $subject
        ]);

        if ($existingTopic) {
            return $existingTopic;
        }

        $topic = new Topic();
        $topic->setName($mainTopic);
        $topic->setSubTopic($subTopic);
        $topic->setSubject($subject);
        $topic->setCreatedAt(new \DateTime());
        $topic->setLecture(null);

        $this->entityManager->persist($topic);
        $this->entityManager->flush();

        return $topic;
    }

    public function populateTopics(): void
    {
        $questionRepository = $this->entityManager->getRepository(Question::class);
        $questions = $questionRepository->findAll();

        $processedTopics = [];

        foreach ($questions as $question) {
            if (!$question->getTopic() || !$question->getSubject()) {
                continue;
            }

            $subTopic = $question->getTopic();
            $subject = $question->getSubject();

            // Create a unique key to avoid duplicates
            $topicKey = $subject->getId() . '_' . $subTopic;

            if (isset($processedTopics[$topicKey])) {
                continue;
            }

            $this->processSingleTopic($subTopic, $subject);
            $processedTopics[$topicKey] = true;
        }
    }

    private function findMainTopicForSubTopic(string $subTopic, array $subjectTopics): ?string
    {
        foreach ($subjectTopics as $mainTopic => $subTopics) {
            if (!is_array($subTopics)) {
                continue;
            }

            // Check if the subtopic exists in the current main topic's subtopics
            foreach ($subTopics as $st) {
                if (strcasecmp(trim($st), trim($subTopic)) === 0) {
                    return $mainTopic;
                }
            }
        }

        return null;
    }
}