<?php

namespace App\Service;

use App\Entity\AccountingQuestion;
use App\Repository\AccountingQuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AccountingQuestionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingQuestionRepository $accountingQuestionRepository,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Import accounting questions from JSON format
     */
    public function importFromJson(array $jsonData, bool $overwrite = false): array
    {
        $imported = [];
        $errors = [];
        $skipped = [];
        $updated = [];

        // Handle both single topic object and array of topic objects
        $topicsData = [];
        
        // Check if the input is a single topic object or array of topics
        if (isset($jsonData['topic']) && isset($jsonData['level']) && isset($jsonData['questions'])) {
            // Single topic object
            $topicsData = [$jsonData];
        } else {
            // Array of topic objects
            $topicsData = $jsonData;
        }

        foreach ($topicsData as $topicData) {
            $topic = isset($topicData['topic']) ? $topicData['topic'] : null;
            $level = isset($topicData['level']) ? $topicData['level'] : null;
            $mainTopic = isset($topicData['main_topic']) ? $topicData['main_topic'] : null;
            $questions = isset($topicData['questions']) ? $topicData['questions'] : [];

            if (!$topic || !$level) {
                $errors[] = "Missing topic or level in data";
                continue;
            }

            // Find or create AccountingTopic
            $accountingTopic = $this->entityManager->getRepository(\App\Entity\AccountingTopic::class)
                ->findOneBy(['mainTopic' => $mainTopic, 'subTopic' => $topic]);
            if (!$accountingTopic) {
                $accountingTopic = new \App\Entity\AccountingTopic();
                $accountingTopic->setMainTopic($mainTopic ?? '');
                $accountingTopic->setSubTopic($topic);
                $this->entityManager->persist($accountingTopic);
            }

            foreach ($questions as $questionData) {
                try {
                    // Check for duplication
                    $existingQuestion = $this->accountingQuestionRepository->findByQuestionId($questionData['id']);
                    if ($existingQuestion) {
                        if ($overwrite) {
                            // Update existing question
                            $this->updateExistingQuestion($existingQuestion, $accountingTopic, $level, $questionData);
                            $updated[] = "Question {$questionData['id']} updated";
                        } else {
                            // Skip existing question
                            $skipped[] = "Question {$questionData['id']} already exists - skipped";
                        }
                        continue;
                    }

                    $question = $this->createQuestionFromData($accountingTopic, $level, $questionData);
                    $this->entityManager->persist($question);
                    $imported[] = $question;
                } catch (\Exception $e) {
                    $questionId = isset($questionData['id']) ? $questionData['id'] : 'unknown';
                    $errors[] = "Error importing question {$questionId}: " . $e->getMessage();
                }
            }
        }

        if (!empty($imported) || !empty($updated)) {
            $this->entityManager->flush();
        }

        return [
            'imported' => count($imported),
            'updated' => count($updated),
            'skipped' => count($skipped),
            'errors' => $errors,
            'skipped_details' => $skipped,
            'updated_details' => $updated
        ];
    }

    /**
     * Create an AccountingQuestion entity from question data
     */
    private function createQuestionFromData(\App\Entity\AccountingTopic $accountingTopic, string $level, array $questionData): AccountingQuestion
    {
        $question = new AccountingQuestion();
        $question->setAccountingTopic($accountingTopic);
        $question->setLevel($level);
        $question->setQuestionId($questionData['id']);
        $question->setQuestionType($questionData['type']);
        
        // Set prompt if available (not all question types have prompts)
        if (isset($questionData['prompt'])) {
            $question->setPrompt($questionData['prompt']);
        } else {
            // For multi-step questions, use a default prompt or the context
            if ($questionData['type'] === 'multi-step' && isset($questionData['context'])) {
                $question->setPrompt('Multi-step question: ' . substr($questionData['context'], 0, 100) . '...');
            } else {
                $question->setPrompt('Question ' . $questionData['id']);
            }
        }

        // Set type-specific fields
        switch ($questionData['type']) {
            case 'tap-to-select':
                $question->setOptions($questionData['options']);
                $question->setAnswer($questionData['answer']);
                break;

            case 'categorise':
                $question->setCategories($questionData['categories']);
                $question->setItems($questionData['items']);
                break;

            case 'true-false':
                $question->setAnswer($questionData['answer']);
                if (isset($questionData['explanation'])) {
                    $question->setExplanation($questionData['explanation']);
                }
                break;

            case 'drag-to-sort':
                $question->setOptions($questionData['items']); // Store items in options field
                $question->setCorrectOrder($questionData['correct_order']);
                break;

            case 'matching':
                $question->setPairs($questionData['pairs']);
                break;

            case 'step-flow':
                // Handle both old format (options/answer) and new format (steps array)
                if (isset($questionData['steps'])) {
                    // New format with steps array
                    $question->setSteps($questionData['steps']);
                } else {
                    // Old format with options and answer
                    $question->setOptions($questionData['options']);
                    $question->setAnswer($questionData['answer']);
                    if (isset($questionData['explanation'])) {
                        $question->setExplanation($questionData['explanation']);
                    }
                }
                break;

            case 'multi-step':
                if (isset($questionData['context'])) {
                    $question->setContext($questionData['context']);
                }
                if (isset($questionData['steps'])) {
                    $question->setSteps($questionData['steps']);
                }
                break;

            default:
                throw new \InvalidArgumentException("Unknown question type: {$questionData['type']}");
        }

        return $question;
    }

    /**
     * Update an existing question with new data
     */
    private function updateExistingQuestion(AccountingQuestion $existingQuestion, \App\Entity\AccountingTopic $accountingTopic, string $level, array $questionData): void
    {
        $existingQuestion->setAccountingTopic($accountingTopic);
        $existingQuestion->setLevel($level);
        $existingQuestion->setQuestionType($questionData['type']);
        
        // Update prompt if available
        if (isset($questionData['prompt'])) {
            $existingQuestion->setPrompt($questionData['prompt']);
        } else {
            // For multi-step questions, use a default prompt or the context
            if ($questionData['type'] === 'multi-step' && isset($questionData['context'])) {
                $existingQuestion->setPrompt('Multi-step question: ' . substr($questionData['context'], 0, 100) . '...');
            } else {
                $existingQuestion->setPrompt('Question ' . $questionData['id']);
            }
        }

        // Update type-specific fields
        switch ($questionData['type']) {
            case 'tap-to-select':
                $existingQuestion->setOptions($questionData['options']);
                $existingQuestion->setAnswer($questionData['answer']);
                break;

            case 'categorise':
                $existingQuestion->setCategories($questionData['categories']);
                $existingQuestion->setItems($questionData['items']);
                break;

            case 'true-false':
                $existingQuestion->setAnswer($questionData['answer']);
                if (isset($questionData['explanation'])) {
                    $existingQuestion->setExplanation($questionData['explanation']);
                }
                break;

            case 'drag-to-sort':
                $existingQuestion->setOptions($questionData['items']);
                $existingQuestion->setCorrectOrder($questionData['correct_order']);
                break;

            case 'matching':
                $existingQuestion->setPairs($questionData['pairs']);
                break;

            case 'step-flow':
                // Handle both old format (options/answer) and new format (steps array)
                if (isset($questionData['steps'])) {
                    // New format with steps array
                    $existingQuestion->setSteps($questionData['steps']);
                } else {
                    // Old format with options and answer
                    $existingQuestion->setOptions($questionData['options']);
                    $existingQuestion->setAnswer($questionData['answer']);
                    if (isset($questionData['explanation'])) {
                        $existingQuestion->setExplanation($questionData['explanation']);
                    }
                }
                break;

            case 'multi-step':
                if (isset($questionData['context'])) {
                    $existingQuestion->setContext($questionData['context']);
                }
                if (isset($questionData['steps'])) {
                    $existingQuestion->setSteps($questionData['steps']);
                }
                break;
        }

        $existingQuestion->setUpdated(new \DateTime());
    }

    /**
     * Get questions grouped by topic and level
     */
    public function getQuestionsByTopicAndLevel(): array
    {
        $mainTopics = $this->accountingQuestionRepository->findDistinctMainTopics();
        $levels = $this->accountingQuestionRepository->findDistinctLevels();
        
        $result = [];
        
        foreach ($mainTopics as $mainTopic) {
            $result[$mainTopic] = [];
            $subtopics = $this->accountingQuestionRepository->findSubtopicsByMainTopic($mainTopic);
            
            foreach ($subtopics as $subtopicData) {
                $subtopic = $subtopicData['topic'];
                $result[$mainTopic][$subtopic] = [];
                
                foreach ($levels as $level) {
                    $questions = $this->accountingQuestionRepository->findByTopicAndLevel($subtopic, $level);
                    if (!empty($questions)) {
                        $result[$mainTopic][$subtopic][$level] = array_map(fn($q) => $q->toArray(), $questions);
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * Get all available main topics
     */
    public function getAvailableMainTopics(): array
    {
        return $this->accountingQuestionRepository->findDistinctMainTopics();
    }

    /**
     * Get subtopics (topics) by main topic
     */
    public function getSubtopicsByMainTopic(string $mainTopic): array
    {
        return $this->accountingQuestionRepository->findSubtopicsByMainTopic($mainTopic);
    }
} 