<?php

namespace App\Service;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuestionTopicService
{
    private const DEEPSEEK_API_URL = 'http://localhost:11434/api/generate';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    public function generateTopicsForNullQuestions(): void
    {
        try {
            $response = $this->httpClient->request('GET', 'https://examquiz.dedicated.co.za/api/question-topics/next');
            $data = json_decode($response->getContent(), true);

            if ($data['status'] === 'OK' && isset($data['question'])) {
                $question = $this->entityManager->getRepository(Question::class)
                    ->find($data['question']['id']);

                if ($question) {
                    $this->generateAndSetTopic($question);
                } else {
                    $this->logger->error('Question not found in database: {id}', [
                        'id' => $data['question']['id']
                    ]);
                }
            } else {
                $this->logger->info('No questions found with null topic');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error getting next question: {error}', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function generateAndSetTopic(Question $question): void
    {
        if (!$question->getSubject() || !$question->getSubject()->getTopics()) {
            $this->logger->info('Skipping question {id} - no subject or topics found', ['id' => $question->getId()]);
            return;
        }

        $subjectTopics = $question->getSubject()->getTopics();
        if (empty($subjectTopics)) {
            $this->logger->info('Skipping question {id} - empty topics array', ['id' => $question->getId()]);
            return;
        }

        $prompt = $this->buildPrompt($question, $subjectTopics);

        try {
            $this->logger->info('Processing question {id}', ['id' => $question->getId()]);

            $this->logger->info($prompt);

            $response = $this->httpClient->request('POST', self::DEEPSEEK_API_URL, [
                'json' => [
                    'model' => 'deepseek-r1:7b',
                    'prompt' => $prompt,
                    'stream' => false
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $topic = $this->cleanResponse(trim($data['response'] ?? ''));

            $this->logger->info('Deepseek response for question {id}: {response}', [
                'id' => $question->getId(),
                'response' => $topic
            ]);

            // If the AI returns 'NO MATCH', set it as the topic
            if ($topic === 'NO MATCH') {
                $this->updateQuestionTopic($question->getId(), 'NO MATCH');
                return;
            }

            // Try to find the best matching topic
            $matchedSubtopic = $this->findBestMatchingSubtopic($topic, $subjectTopics);

            if ($matchedSubtopic) {
                $this->updateQuestionTopic($question->getId(), $matchedSubtopic);
            } else {
                $this->updateQuestionTopic($question->getId(), 'NO MATCH');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing question {id}: {error}', [
                'id' => $question->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function updateQuestionTopic(int $questionId, string $topic): void
    {
        try {
            $response = $this->httpClient->request('PUT', 'https://examquiz.dedicated.co.za/api/question-topics/update/' . $questionId, [
                'json' => ['topic' => $topic]
            ]);

            $result = json_decode($response->getContent(), true);

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
            $this->logger->error('Error calling update endpoint for question {id}: {error}', [
                'id' => $questionId,
                'error' => $e->getMessage()
            ]);
        }
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

    private function buildPrompt(Question $question, array $subjectTopics): string
    {
        $topicsList = $this->formatTopicsForPrompt($subjectTopics);

        return sprintf(
            "You are a topic classifier. Your ONLY task is to return an EXACT topic from the list below. Do not think, analyze, or explain. Just return the topic.

QUESTION: %s
CONTEXT: %s
ANSWER: %s

TOPICS:
%s

RULES:
1. Return ONLY the exact topic text
2. Do not include any other text
3. If no match, return 'NO MATCH'",
            $question->getQuestion() ?? '',
            $question->getContext() ?? '',
            $question->getAnswer() ?? '',
            $topicsList
        );
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

        // First try exact matches
        foreach ($subjectTopics as $mainTopic => $subtopics) {
            if (is_array($subtopics)) {
                foreach ($subtopics as $subtopic) {
                    if ($topic === strtolower($subtopic)) {
                        return $subtopic;
                    }
                }
            }
        }

        // Then try partial matches with different strategies
        foreach ($subjectTopics as $mainTopic => $subtopics) {
            if (is_array($subtopics)) {
                foreach ($subtopics as $subtopic) {
                    $subtopicLower = strtolower($subtopic);

                    // Strategy 1: Check if all words in the topic are in the subtopic
                    $topicWords = explode(' ', $topic);
                    $subtopicWords = explode(' ', $subtopicLower);

                    $allWordsFound = true;
                    foreach ($topicWords as $word) {
                        $wordFound = false;
                        foreach ($subtopicWords as $subtopicWord) {
                            if (strpos($subtopicWord, $word) !== false) {
                                $wordFound = true;
                                break;
                            }
                        }
                        if (!$wordFound) {
                            $allWordsFound = false;
                            break;
                        }
                    }

                    if ($allWordsFound) {
                        return $subtopic;
                    }

                    // Strategy 2: Check if the topic is a significant part of the subtopic
                    if (strpos($subtopicLower, $topic) !== false && strlen($topic) > 5) {
                        return $subtopic;
                    }
                }
            }
        }

        return null;
    }

    public function getNextQuestionWithNoTopic(): ?Question
    {
        try {
            $question = $this->entityManager->getRepository(Question::class)
                ->findOneBy(['topic' => null], ['id' => 'ASC']);

            if (!$question) {
                $this->logger->info('No questions found with null topic');
                return null;
            }

            $this->logger->info('Found next question with no topic: {id}', [
                'id' => $question->getId()
            ]);

            return $question;
        } catch (\Exception $e) {
            $this->logger->error('Error getting next question with no topic: {error}', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}