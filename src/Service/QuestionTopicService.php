<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuestionTopicService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private $openAiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private QuestionTopicUpdateService $questionTopicUpdateService,
        string $openAiKey
    ) {
        $this->openAiKey = $openAiKey;
    }

    public function generateTopicsForNullQuestions(int $grade): void
    {
        try {
            for ($i = 0; $i < 1000; $i++) {
                $question = $this->getNextQuestionWithNoTopic($grade);

                if ($question) {
                    $subject = $question->getSubject();
                    $subjectTopics = $subject ? $subject->getTopics() : [];

                    $questionData = [
                        'id' => $question->getId(),
                        'question' => $question->getQuestion(),
                        'context' => $question->getContext(),
                        'answer' => $question->getAnswer(),
                        'subject_id' => $subject ? $subject->getId() : null,
                        'subject_topics' => $subjectTopics,
                        'image_path' => $question->getImagePath(),
                        'question_image_path' => $question->getQuestionImagePath()
                    ];

                    $this->generateAndSetTopic($questionData);
                } else {
                    $this->logger->info('No more questions found with null topic for grade {grade}', [
                        'grade' => $grade
                    ]);
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing questions for grade {grade}: {error}', [
                'grade' => $grade,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function generateAndSetTopic(array $questionData): void
    {
        if (!isset($questionData['subject_id'])) {
            $this->logger->info('Skipping question {id} - no subject found', ['id' => $questionData['id']]);
            return;
        }

        try {
            $this->logger->info('Processing question {id}', ['id' => $questionData['id']]);

            $prompt = $this->buildPrompt($questionData);

            $this->logger->info($prompt);

            $response = $this->httpClient->request('POST', self::OPENAI_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a topic classifier. Your task is to return an exact topic from the provided list.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 50
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $topic = $this->cleanResponse(trim($data['choices'][0]['message']['content'] ?? ''));

            $this->logger->info('OpenAI response for question {id}: {response}', [
                'id' => $questionData['id'],
                'response' => $topic
            ]);

            // If the AI returns 'NO MATCH', set it as the topic
            if ($topic === 'NO MATCH') {
                $this->updateQuestionTopic($questionData['id'], 'NO MATCH WITH IMAGE');
                return;
            }

            // Try to find the best matching topic
            $matchedSubtopic = $this->findBestMatchingSubtopic($topic, $questionData['subject_topics'] ?? []);

            if ($matchedSubtopic) {
                $this->updateQuestionTopic($questionData['id'], $matchedSubtopic);
            } else {
                $this->updateQuestionTopic($questionData['id'], 'NO MATCH WITH IMAGE');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing question {id}: {error}', [
                'id' => $questionData['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function buildPrompt(array $questionData): string
    {
        $topicsList = $this->formatTopicsForPrompt($questionData['subject_topics'] ?? []);
        $imageInfo = '';

        if (!empty($questionData['image_path'])) {
            $imageInfo .= "\nIMAGE PATH: " . $questionData['image_path'];
        }
        if (!empty($questionData['question_image_path'])) {
            $imageInfo .= "\nQUESTION IMAGE PATH: " . $questionData['question_image_path'];
        }

        return sprintf(
            "You are a topic classifier. Your ONLY task is to return an EXACT topic from the list below. Do not think, analyze, or explain. Just return the topic.

QUESTION: %s
CONTEXT: %s
ANSWER: %s%s
TOPICS:
%s

RULES:
1. Return ONLY the exact topic text
2. Do not include any other text
3. If no match, return 'NO MATCH'
4. DO NOT return the answer or any part of the answer
5. Focus only on the question and context to determine the topic",
            $questionData['question'] ?? '',
            $questionData['context'] ?? '',
            $questionData['answer'] ?? '',
            $imageInfo,
            $topicsList
        );
    }

    private function formatTopicsForPrompt(array $subjectTopics): string
    {
        $formattedTopics = [];
        foreach ($subjectTopics as $subtopics) {
            if (is_array($subtopics)) {
                foreach ($subtopics as $subtopic) {
                    $formattedTopics[] = "- " . $subtopic;
                }
            } else {
                $formattedTopics[] = "- " . $subtopics;
            }
        }
        return implode("\n", $formattedTopics);
    }

    private function cleanResponse(string $response): string
    {
        // Remove the <think> tags and their content
        $response = preg_replace('/<think>.*?<\/think>/s', '', $response);

        // Remove any leading/trailing whitespace and newlines
        $response = trim($response);

        return $response;
    }

    private function findBestMatchingSubtopic(string $topic, array $subjectTopics): ?string
    {
        $topic = strtolower(trim($topic));

        foreach ($subjectTopics as $mainTopic => $subtopics) {
            if (is_array($subtopics)) {
                foreach ($subtopics as $subtopic) {
                    if ($topic === strtolower($subtopic)) {
                        return $subtopic;
                    }
                }
            }
        }

        return null;
    }

    private function updateQuestionTopic(int $questionId, string $topic): void
    {
        try {
            $this->logger->info('Updating topic for question {id} to {topic}', [
                'id' => $questionId,
                'topic' => $topic
            ]);

            $result = $this->questionTopicUpdateService->updateQuestionTopic($questionId, $topic);

            if ($result['status'] === 'OK') {
                $this->logger->info('Successfully updated topic for question {id} to {topic}', [
                    'id' => $questionId,
                    'topic' => $topic
                ]);
            } else {
                $this->logger->error('Failed to update topic for question {id}: {message}', [
                    'id' => $questionId,
                    'message' => $result['message'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error updating topic for question {id}: {error}', [
                'id' => $questionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getNextQuestionWithNoTopic(int $grade): ?Question
    {
        try {
            $question = $this->entityManager->getRepository(Question::class)
                ->createQueryBuilder('q')
                ->join('q.subject', 's')
                ->where('q.topic IS NULL OR q.topic = :noMatch')
                ->andWhere('s.grade = :grade')
                ->setParameter('grade', $grade)
                ->setParameter('noMatch', 'NO MATCH')
                ->orderBy('q.id', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$question) {
                $this->logger->info('No questions found with null or NO MATCH topic for grade {grade}', [
                    'grade' => $grade
                ]);
                return null;
            }

            // Ensure topics is an array
            $subject = $question->getSubject();
            if ($subject) {
                $topics = $subject->getTopics();
                if (!is_array($topics)) {
                    $subject->setTopics([]);
                }
            }

            $this->logger->info('Found next question with no topic: {id} for grade {grade}', [
                'id' => $question->getId(),
                'grade' => $grade
            ]);

            return $question;
        } catch (\Exception $e) {
            $this->logger->error('Error getting next question with no topic for grade {grade}: {error}', [
                'grade' => $grade,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}