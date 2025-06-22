<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Subject;
use App\Entity\Learner;

class QuestionImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    public function importFromJson(string $jsonContent): array
    {
        $questions = json_decode($jsonContent, true);
        $importedQuestions = [];
        $errors = [];

        foreach ($questions as $questionData) {
            try {
                $subject = $this->entityManager->getRepository(Subject::class)->findOneBy(['id' => $questionData['subject']]);
                $capturer = $this->entityManager->getRepository(Learner::class)->findOneBy(['id' => $questionData['capturer']]);
                $reviewer = $this->entityManager->getRepository(Learner::class)->findOneBy(['id' => $questionData['reviewer']]);

                // Validate that subject exists
                if (!$subject) {
                    throw new \Exception("Subject with ID {$questionData['subject']} not found");
                }

                // Handle topic information if provided
                $topic = null;
                if (isset($questionData['main_topic']) && isset($questionData['sub_topic'])) {
                    $topic = $this->findOrCreateTopic($questionData['sub_topic'], $questionData['main_topic'], $subject);
                }

                $question = new Question();

                // Map the JSON data to the Question entity
                $question->setQuestion($questionData['question']);
                $question->setType($questionData['type']);
                $question->setContext($questionData['context']);
                $question->setAnswer($questionData['answer']);
                $question->setOptions($questionData['options']);
                $question->setTerm($questionData['term']);
                $question->setImagePath($questionData['image_path']);
                $question->setExplanation($questionData['explanation']);
                $question->setHigherGrade($questionData['higher_grade']);
                $question->setActive($questionData['active']);
                $question->setYear($questionData['year']);
                $question->setAnswerImage($questionData['answer_image']);

                $question->setQuestionImagePath($questionData['question_image_path']);
                $question->setImagePath($questionData['image_path']);
                $question->setAnswerImage($questionData['answer_image']);

                $question->setComment($questionData['comment']);
                $question->setPosted($questionData['posted']);
                $question->setAiExplanation($questionData['ai_explanation']);
                $question->setCurriculum($questionData['curriculum']);
                $question->setSubject($subject);
                $question->setCapturer($capturer);
                $question->setReviewer($reviewer);

                // Set topic to sub_topic if topic information was provided
                if (isset($questionData['sub_topic'])) {
                    $question->setTopic($questionData['sub_topic']);
                }

                // Set timestamps
                if (isset($questionData['created'])) {
                    $question->setCreated(new \DateTime($questionData['created']));
                }
                if (isset($questionData['updated'])) {
                    $question->setUpdated(new \DateTime($questionData['updated']));
                }
                if (isset($questionData['reviewed_at'])) {
                    $question->setReviewedAt(new \DateTime($questionData['reviewed_at']));
                }

                $this->entityManager->persist($question);
                $importedQuestions[] = $question;
            } catch (\Exception $e) {
                $errors[] = [
                    'question' => $questionData['question'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        if (empty($errors)) {
            $this->entityManager->flush();
        }

        return [
            'imported' => $importedQuestions,
            'errors' => $errors
        ];
    }

    private function findOrCreateTopic(string $subTopic, string $mainTopic, ?Subject $subject): ?Topic
    {
        if (!$subject) {
            throw new \Exception("Subject is required to create or find a topic");
        }

        $topicRepository = $this->entityManager->getRepository(Topic::class);
        
        // Try to find existing topic by sub_topic and subject
        $existingTopic = $topicRepository->findOneBySubTopicAndSubject($subTopic, $subject->getId());
        
        if ($existingTopic) {
            return $existingTopic;
        }
        
        // Create new topic if not found
        $newTopic = new Topic();
        $newTopic->setName($mainTopic);
        $newTopic->setSubTopic($subTopic);
        $newTopic->setSubject($subject);
        $newTopic->setCreatedAt(new \DateTime());
        
        $this->entityManager->persist($newTopic);
        
        return $newTopic;
    }
}