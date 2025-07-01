<?php

namespace App\Service;

use App\Entity\AccountingQuestion;
use App\Repository\AccountingQuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AccountingQuestionGeneratorService
{
    public function __construct(
        private OpenAIService $openAIService,
        private EntityManagerInterface $entityManager,
        private AccountingQuestionRepository $accountingQuestionRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Generate a single accounting question using AI
     */
    public function generateQuestion(
        string $topic,
        string $level,
        string $questionType,
        string $mainTopic
    ): array {
        // Fetch existing questions for this topic and type to avoid duplication
        $existingQuestions = $this->getExistingQuestions($topic, $questionType, $level);
        
        $prompt = $this->buildPrompt($topic, $level, $questionType, $existingQuestions);
        
        try {
            $response = $this->openAIService->getClient()->request('POST', $this->openAIService->getApiUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAIService->getApiKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert accounting educator who creates engaging quiz questions for high school students. You must follow the exact JSON format specified and ensure all questions are educationally sound and appropriate for the specified level. IMPORTANT: Avoid creating questions that are too similar to existing ones provided.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7
                ]
            ]);

            $data = json_decode($response->getContent(), true);
            $content = $data['choices'][0]['message']['content'] ?? 'Failed to generate question.';

            // Log token usage
            $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $data['usage']['completion_tokens'] ?? 0;
            $totalTokens = $data['usage']['total_tokens'] ?? 0;
            $this->logger->debug("Token Usage for Question Generation:\n" .
                "Prompt tokens: {$promptTokens}\n" .
                "Completion tokens: {$completionTokens}\n" .
                "Total tokens: {$totalTokens}");

            // Parse the JSON response
            $questionData = $this->parseQuestionResponse($content);
            
            if (!$questionData) {
                throw new \Exception('Failed to parse AI response into valid question format');
            }

            // Generate a unique question ID
            $questionId = $this->generateUniqueQuestionId($topic, $questionType);
            $questionData['id'] = $questionId;

            // Save the question to the database
            $savedQuestion = $this->saveQuestion($topic, $level, $mainTopic, $questionData);

            return [
                'success' => true,
                'question' => $savedQuestion->toArray(),
                'token_usage' => [
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error generating accounting question: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate multiple questions for a topic and level
     */
    public function generateMultipleQuestions(
        string $topic,
        string $level,
        string $mainTopic,
        int $count = 5,
        array $questionTypes = ['tap-to-select', 'categorise', 'true-false', 'drag-to-sort', 'matching']
    ): array {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $generatedQuestions = []; // Track questions generated in this batch

        for ($i = 0; $i < $count; $i++) {
            $questionType = $questionTypes[$i % count($questionTypes)];
            
            // Get existing questions plus any generated in this batch
            $existingQuestions = $this->getExistingQuestions($topic, $questionType, $level);
            $allExistingQuestions = array_merge($existingQuestions, $generatedQuestions);
            
            $prompt = $this->buildPrompt($topic, $level, $questionType, $allExistingQuestions);
            
            try {
                $response = $this->openAIService->getClient()->request('POST', $this->openAIService->getApiUrl(), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->openAIService->getApiKey(),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4-turbo',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are an expert accounting educator who creates engaging quiz questions for high school students. You must follow the exact JSON format specified and ensure all questions are educationally sound and appropriate for the specified level. IMPORTANT: Avoid creating questions that are too similar to existing ones provided.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.7
                    ]
                ]);

                $data = json_decode($response->getContent(), true);
                $content = $data['choices'][0]['message']['content'] ?? 'Failed to generate question.';

                // Parse the JSON response
                $questionData = $this->parseQuestionResponse($content);
                
                if (!$questionData) {
                    throw new \Exception('Failed to parse AI response into valid question format');
                }

                // Generate a unique question ID
                $questionId = $this->generateUniqueQuestionId($topic, $questionType);
                $questionData['id'] = $questionId;

                // Save the question to the database
                $savedQuestion = $this->saveQuestion($topic, $level, $mainTopic, $questionData);

                // Add to generated questions for this batch
                $generatedQuestions[] = [
                    'prompt' => $savedQuestion->getPrompt(),
                    'options' => $savedQuestion->getOptions(),
                    'answer' => $savedQuestion->getAnswer(),
                    'categories' => $savedQuestion->getCategories(),
                    'items' => $savedQuestion->getItems(),
                    'pairs' => $savedQuestion->getPairs(),
                    'correctOrder' => $savedQuestion->getCorrectOrder(),
                    'explanation' => $savedQuestion->getExplanation(),
                    'context' => $savedQuestion->getContext(),
                    'steps' => $savedQuestion->getSteps()
                ];

                $result = [
                    'success' => true,
                    'question' => $savedQuestion->toArray(),
                    'token_usage' => [
                        'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                        'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                        'total_tokens' => $data['usage']['total_tokens'] ?? 0
                    ]
                ];
                
                $successCount++;
                $results[] = $result;
                
            } catch (\Exception $e) {
                $this->logger->error('Error generating accounting question: ' . $e->getMessage());
                $errorCount++;
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Add a small delay to avoid rate limiting
            if ($i < $count - 1) {
                sleep(1);
            }
        }

        return [
            'total_requested' => $count,
            'successful' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }

    /**
     * Get existing questions for the topic and question type to avoid duplication
     */
    private function getExistingQuestions(string $topic, string $questionType, ?string $level = null): array
    {
        $qb = $this->accountingQuestionRepository->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->where('at.subTopic = :topic')
            ->andWhere('aq.questionType = :questionType')
            ->andWhere('aq.active = :active')
            ->setParameter('topic', $topic)
            ->setParameter('questionType', $questionType)
            ->setParameter('active', true);

        // Add level filter if provided to reduce payload and focus on similar difficulty
        if ($level) {
            $qb->andWhere('aq.level = :level')
               ->setParameter('level', $level);
        }

        $existingQuestions = $qb->orderBy('aq.created', 'DESC')
            ->setMaxResults(5) // Reduced from 10 to 5 to keep payload smaller
            ->getQuery()
            ->getResult();

        $formattedQuestions = [];
        foreach ($existingQuestions as $question) {
            $formattedQuestions[] = [
                'prompt' => $question->getPrompt(),
                'options' => $question->getOptions(),
                'answer' => $question->getAnswer(),
                'categories' => $question->getCategories(),
                'items' => $question->getItems(),
                'pairs' => $question->getPairs(),
                'correctOrder' => $question->getCorrectOrder(),
                'explanation' => $question->getExplanation(),
                'context' => $question->getContext(),
                'steps' => $question->getSteps()
            ];
        }

        return $formattedQuestions;
    }

    /**
     * Build the prompt for question generation
     */
    private function buildPrompt(string $topic, string $level, string $questionType, array $existingQuestions = []): string
    {
        // Map levels to grade context
        $gradeContext = $this->getGradeContext($level);
        
        // Format existing questions for the prompt
        $existingQuestionsText = '';
        if (!empty($existingQuestions)) {
            $existingQuestionsText = "\n\nEXISTING QUESTIONS TO AVOID DUPLICATION:\n";
            foreach ($existingQuestions as $index => $question) {
                $existingQuestionsText .= "Q" . ($index + 1) . ": " . $question['prompt'] . "\n";
                
                if (isset($question['prompt'])) {
                    $existingQuestionsText .= "  Prompt: " . $question['prompt'] . "\n";
                }
                if (isset($question['options']) && is_array($question['options'])) {
                    $existingQuestionsText .= "  Options: " . implode(', ', $question['options']) . "\n";
                }
                if (isset($question['answer'])) {
                    $existingQuestionsText .= "  Answer: " . $question['answer'] . "\n";
                }
                if (isset($question['items']) && is_array($question['items'])) {
                    $itemsList = [];
                    foreach ($question['items'] as $item => $category) {
                        $itemsList[] = "{$item}→{$category}";
                    }
                    $existingQuestionsText .= "  Items: " . implode(', ', $itemsList) . "\n";
                }
                if (isset($question['pairs']) && is_array($question['pairs'])) {
                    $pairsList = [];
                    foreach ($question['pairs'] as $item => $category) {
                        $pairsList[] = "{$item}→{$category}";
                    }
                    $existingQuestionsText .= "  Pairs: " . implode(', ', $pairsList) . "\n";
                }
                if (isset($question['steps']) && is_array($question['steps'])) {
                    $existingQuestionsText .= "  Steps: " . count($question['steps']) . " steps\n";
                    // Show first step details for context
                    if (count($question['steps']) > 0) {
                        $firstStep = $question['steps'][0];
                        if (isset($firstStep['prompt'])) {
                            $existingQuestionsText .= "  First step: " . $firstStep['prompt'] . "\n";
                        }
                    }
                }
                $existingQuestionsText .= "\n";
            }
        }
        
        $basePrompt = "Generate a {$level} accounting quiz question in JSON format. 
The topic is \"{$topic}\" and the question type is \"{$questionType}\".

Grade Level Context: {$gradeContext}

Rules:
- Only 1 correct answer
- Include a playful tone with 1–2 emojis
- Use USA accounting terminology (e.g., Net Sales, Expenses, Income Tax)
- Keep the question short and clear (max 20 words)
- Avoid using \"True or False:\" in the prompt
- Adjust complexity and vocabulary to match the grade level
- IMPORTANT: Create a question that is significantly different from the existing questions provided
- {$existingQuestionsText}
- Use this JSON format exactly:{$existingQuestionsText}";

        switch ($questionType) {
            case 'tap-to-select':
                return $basePrompt . '

{
  "questions": [
    {
      "id": "unique_id_here",
      "type": "tap-to-select",
      "prompt": "Is Rent Revenue an income or an expense? 💰",
      "options": ["Income", "Expense", "Other Income", "Other Expense"],
      "answer": "Income"
    }
  ]
}';

            case 'categorise':
                return $basePrompt . '

{
  "questions": [
    {
      "id": "unique_id_here",
      "type": "categorise",
      "prompt": "Drag each item to the correct category. 📊",
      "categories": ["Income", "Operating Expense"],
      "items": {
        "Rent Income": "Income",
        "Packing Material": "Operating Expense",
        "Service Fees": "Income",
        "Audit Fees": "Operating Expense",
        "Salaries": "Operating Expense",
        "Interest Income": "Income"
      }
    }
  ]
}';

            case 'true-false':
                return $basePrompt . '

{
  "questions": [
    {
      "id": "unique_id_here",
      "type": "true-false",
      "prompt": "Depreciation is an income item. 🤔",
      "answer": "False",
      "explanation": "Depreciation is an operating expense, not income."
    }
  ]
}';

            case 'drag-to-sort':
                return $basePrompt . '

{
  "questions": [
    {
      "id": "unique_id_here",
      "type": "drag-to-sort",
      "prompt": "Put these in the correct order as they appear on the income statement. 📋",
      "items": [
        "Sales",
        "Cost of Sales",
        "Gross Profit",
        "Operating Expenses",
        "Net Profit"
      ],
      "correct_order": [
        "Sales",
        "Cost of Sales",
        "Gross Profit",
        "Operating Expenses",
        "Net Profit"
      ]
    }
  ]
}';

            case 'matching':
                return $basePrompt . '

{
  "questions": [
    {
      "id": "unique_id_here",
      "type": "matching",
      "prompt": "Match each item to the correct section. 🔗",
      "pairs": {
        "Sales": "Income",
        "Bad Debts": "Operating Expense",
        "Directors\' Fees": "Operating Expense",
        "Interest Income": "Other Income"
      }
    }
  ]
}';

            case 'step-flow':
                return $basePrompt . '

{
  "questions": [
    {
      "id": "is_l2_q2",
      "type": "step-flow",
      "steps": [
        {
          "prompt": "What is the formula to calculate Gross Profit?",
          "options": [
            "Gross Profit = Net Sales \u2212 Cost of Goods Sold",
            "Gross Profit = Operating Income \u2212 Expenses",
            "Gross Profit = Revenue + Other Income"
          ],
          "answer": "Gross Profit = Net Sales \u2212 Cost of Goods Sold",
          "explanation": "Gross Profit is calculated by subtracting the Cost of Goods Sold from the Net Sales."
        },
        {
          "prompt": "Net Sales: $750,000; Cost of Goods Sold: $500,000. What is the Gross Profit?",
          "options": [
            "$250,000",
            "$300,000",
            "$200,000"
          ],
          "answer": "$250,000",
          "explanation": "Gross Profit is calculated by subtracting the Cost of Goods Sold from the Net Sales."
        }
      ]
    }
  ]
}';

            case 'multi-step':
                // Determine step count and level number based on level
                $levelNumber = '2'; // Default for Level 2
                $stepCount = 4;
                
                if (strpos($level, 'Level 3') !== false) {
                    $levelNumber = '3';
                    $stepCount = 5;
                } elseif (strpos($level, 'Level 4') !== false) {
                    $levelNumber = '4';
                    $stepCount = 6;
                }
                
                // Build steps array with exact format but different counts
                $steps = [
                    [
                        "prompt" => "Step 1: What is the Gross Profit?",
                        "options" => [
                            "$340,000",
                            "$300,000",
                            "$360,000"
                        ],
                        "answer" => "$340,000",
                        "explanation" => "Gross Profit = Net Sales - Cost of Goods Sold = $850,000 - $510,000"
                    ],
                    [
                        "prompt" => "Step 2: What are the Total Operating Expenses after adjustments?",
                        "options" => [
                            "$208,000",
                            "$212,000",
                            "$216,000"
                        ],
                        "answer" => "$212,000",
                        "explanation" => "Add Salaries ($150k), Utilities ($24k), Advertising ($36k), and expired Prepaid Insurance ($2k)"
                    ],
                    [
                        "prompt" => "Step 3: What is the Operating Income?",
                        "options" => [
                            "$128,000",
                            "$130,000",
                            "$140,000"
                        ],
                        "answer" => "$128,000",
                        "explanation" => "Operating Income = Gross Profit - Operating Expenses = $340,000 - $212,000"
                    ],
                    [
                        "prompt" => "Step 4: What is the Net Income before Tax?",
                        "options" => [
                            "$129,500",
                            "$134,000",
                            "$133,500"
                        ],
                        "answer" => "$129,500",
                        "explanation" => "Add Interest Income ($6,000), subtract Interest Expense ($4,500): $128,000 + $6,000 - $4,500"
                    ]
                ];
                
                // Add additional steps for higher levels
                if ($stepCount >= 5) {
                    $steps[] = [
                        "prompt" => "Step 5: What is the Income Tax Expense?",
                        "options" => [
                            "$36,260",
                            "$35,000",
                            "$38,000"
                        ],
                        "answer" => "$36,260",
                        "explanation" => "Tax = 28% of $129,500 = $36,260"
                    ];
                }
                
                if ($stepCount >= 6) {
                    $steps[] = [
                        "prompt" => "Step 6: What is the Net Income after Tax?",
                        "options" => [
                            "$93,240",
                            "$94,000",
                            "$91,000"
                        ],
                        "answer" => "$93,240",
                        "explanation" => "Net Income = $129,500 - $36,260"
                    ];
                }
                
                $stepsJson = json_encode($steps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
                return $basePrompt . '

{
  "questions": [
    {
      "id": "is_l' . $levelNumber . '_q1",
      "type": "multi-step",
      "context": "You are preparing the Income Statement for Summit Sports Inc. for the year ended December 31, 2024.\nUse the following information:\n\n<table>\n<tr><th>Item</th><th>Amount</th></tr>\n<tr><td>Net Sales</td><td>$850,000</td></tr>\n<tr><td>Cost of Goods Sold</td><td>$510,000</td></tr>\n<tr><td>Salaries Expense</td><td>$150,000</td></tr>\n<tr><td>Utilities Expense</td><td>$24,000</td></tr>\n<tr><td>Advertising Expense</td><td>$36,000</td></tr>\n<tr><td>Interest Income</td><td>$6,000</td></tr>\n<tr><td>Interest Expense</td><td>$4,500</td></tr>\n</table>\n\nAdditional Adjustments:\n<table>\n<tr><th>Adjustment</th><th>Detail</th></tr>\n<tr><td>Unbilled Revenue</td><td>$7,000 earned this year but not yet billed</td></tr>\n<tr><td>Prepaid Insurance</td><td>1 month ($2,000) expired and must be recognized</td></tr>\n</table>",
      "steps": ' . $stepsJson . '
    }
  ]
}';

            default:
                throw new \InvalidArgumentException("Unsupported question type: {$questionType}");
        }
    }

    /**
     * Get grade-level context for the specified level
     */
    private function getGradeContext(string $level): string
    {
        switch ($level) {
            case 'Level 1: Basics':
                return 'This is for students in Grades 8-9 (ages 13-15). Use simple vocabulary, basic concepts, and straightforward explanations. Focus on fundamental accounting principles with minimal complexity.';
            
            case 'Level 2: Core Practice':
                return 'This is for students in Grades 10-11 (ages 15-17). Use intermediate vocabulary and concepts. Include basic calculations and application of accounting principles.';
            
            case 'Level 3: Advanced':
                return 'This is for students in Grade 12 (ages 17-18). Use advanced vocabulary and complex concepts. Include detailed calculations, analysis, and comprehensive problem-solving scenarios.';
            
            case 'Level 4: Expert':
                return 'This is for advanced Grade 12 students (ages 17-18) preparing for university-level accounting. Use sophisticated vocabulary, complex scenarios, and require deep analytical thinking.';
            
            default:
                return 'This is for high school accounting students. Adjust complexity based on the level specified.';
        }
    }

    /**
     * Parse the AI response to extract question data
     */
    private function parseQuestionResponse(string $content): ?array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonString = $matches[0];
            $data = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['questions'][0])) {
                return $data['questions'][0];
            }
        }

        // If no valid JSON found, try to parse the entire content
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['questions'][0])) {
            return $data['questions'][0];
        }

        $this->logger->error('Failed to parse AI response: ' . $content);
        return null;
    }

    /**
     * Generate a unique question ID
     */
    private function generateUniqueQuestionId(string $topic, string $questionType): string
    {
        $prefix = strtolower(str_replace([' ', '-'], ['_', '_'], $topic));
        $typePrefix = str_replace('-', '_', $questionType);
        $timestamp = time();
        $random = substr(md5(uniqid()), 0, 4);
        
        $questionId = "ai_{$prefix}_{$typePrefix}_{$timestamp}_{$random}";
        
        // Check if ID already exists and generate a new one if needed
        $attempts = 0;
        while ($this->accountingQuestionRepository->findByQuestionId($questionId) && $attempts < 10) {
            $random = substr(md5(uniqid()), 0, 4);
            $questionId = "ai_{$prefix}_{$typePrefix}_{$timestamp}_{$random}";
            $attempts++;
        }
        
        return $questionId;
    }

    /**
     * Save the generated question to the database
     */
    private function saveQuestion(string $topic, string $level, string $mainTopic, array $questionData): AccountingQuestion
    {
        $question = new AccountingQuestion();
        $accountingTopic = $this->entityManager->getRepository(\App\Entity\AccountingTopic::class)
            ->findOneBy(['mainTopic' => $mainTopic, 'subTopic' => $topic]);
        if (!$accountingTopic) {
            $accountingTopic = new \App\Entity\AccountingTopic();
            $accountingTopic->setMainTopic($mainTopic ?? '');
            $accountingTopic->setSubTopic($topic);
            $this->entityManager->persist($accountingTopic);
        }
        $question->setAccountingTopic($accountingTopic);
        $question->setLevel($level);
        $question->setQuestionId($questionData['id']);
        $question->setQuestionType($questionData['type']);
        
        // Set prompt
        if (isset($questionData['prompt'])) {
            $question->setPrompt($questionData['prompt']);
        } else {
            $question->setPrompt('AI Generated Question ' . $questionData['id']);
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
                $question->setOptions($questionData['items']); // items are stored in options field
                $question->setCorrectOrder($questionData['correct_order']);
                break;

            case 'matching':
                $question->setPairs($questionData['pairs']);
                break;

            case 'step-flow':
                if (isset($questionData['steps'])) {
                    // New format with steps array
                    $question->setSteps($questionData['steps']);
                } else {
                    // Old format with options and answer (for backward compatibility)
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

        $this->entityManager->persist($question);
        $this->entityManager->flush();

        return $question;
    }
} 