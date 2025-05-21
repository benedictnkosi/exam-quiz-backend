<?php

namespace App\Service;

use App\Entity\Question;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StepGenerationService
{
    private const PROMPT_RULES = [
        'steps' => [
            'system' => 'You are a maths education expert designing step-by-step interactive learning content for high school students (Grades 8–12). Return ONLY the JSON object, no markdown code blocks or additional text.',
            'instructions' => [
                '1. Break down the question into multiple logical steps.',
                '2. Each step should be educational and interactive.',
                '3. Use LaTeX for all mathematical expressions.',
                '4. Wrap plain English in \\text{} commands.',
                '5. Include common misconceptions as distractors.',
                '6. Provide clear hints and explanations.',
                '7. Format for mobile display with \\newline where needed.',
                '8. Return the result in the specified JSON format.',
                '9. DO NOT use markdown code blocks or ```json in your response.',
                '10. Return ONLY the JSON object, no additional text or formatting.',
                '11. DO NOT wrap LaTeX expressions in dollar signs ($).'
            ]
        ]
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params
    ) {
    }

    public function generateSteps(Question $question, ?OutputInterface $output = null): ?array
    {
        $apiKey = $this->params->get('openai_api_key');
        $apiUrl = 'https://api.openai.com/v1/chat/completions';
        $maxRetries = 1;
        $retryCount = 0;
        $shouldRetry = true;

        while ($shouldRetry) {
            try {
                $prompt = $this->buildPrompt($question);

                if ($output) {
                    $output->writeln("Generating steps for question ID: " . $question->getId());
                }

                // Prepare messages array
                $messages = [
                    [
                        'role' => 'system',
                        'content' => self::PROMPT_RULES['steps']['system']
                    ],
                    [
                        'role' => 'user',
                        'content' => []
                    ]
                ];

                // Add text content
                $messages[1]['content'][] = [
                    'type' => 'text',
                    'text' => $prompt
                ];

                // Add image URLs if they exist
                if ($question->getImagePath()) {
                    $imageUrl = 'https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=' . $question->getImagePath();
                    $messages[1]['content'][] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => $imageUrl]
                    ];
                }
                if ($question->getQuestionImagePath()) {
                    $imageUrl = 'https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=' . $question->getQuestionImagePath();
                    $messages[1]['content'][] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => $imageUrl]
                    ];
                }

                $response = $this->httpClient->request('POST', $apiUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4.1-mini',
                        'messages' => $messages
                    ]
                ]);

                $result = $response->toArray();

                if (!isset($result['choices'][0]['message']['content'])) {
                    throw new \Exception('Invalid response format from OpenAI API');
                }

                $content = $result['choices'][0]['message']['content'];

                if ($output) {
                    $output->writeln("AI Response: " . $content);
                }

                // Clean up the response content
                $content = $this->cleanResponseContent($content);

                // Parse and validate the JSON response
                $steps = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Failed to parse steps JSON: ' . json_last_error_msg() . "\nContent: " . $content);
                }

                // Validate required fields
                $this->validateSteps($steps);

                // Format LaTeX expressions
                $steps = $this->formatStepsLatex($steps);

                // If we get here, the steps were generated successfully
                $shouldRetry = false;
                return $steps;

            } catch (\Exception $e) {
                if ($output) {
                    $output->writeln("Error: " . $e->getMessage());
                }

                if ($retryCount < $maxRetries) {
                    $retryCount++;
                    if ($output) {
                        $output->writeln("Retrying (Attempt $retryCount of $maxRetries)");
                    }

                    // Sleep for a short duration before retrying (exponential backoff)
                    $sleepTime = pow(2, $retryCount) * 1000000; // Convert to microseconds
                    usleep($sleepTime);

                    continue;
                } else {
                    if ($output) {
                        $output->writeln("Max retries reached. Giving up.");
                    }
                    return null;
                }
            }
        }

        return null;
    }

    private function cleanResponseContent(string $content): string
    {
        // Remove markdown code block markers
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        // Remove any leading/trailing whitespace
        $content = trim($content);

        // Remove any non-JSON text before or after the JSON object
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        return $content;
    }

    private function buildPrompt(Question $question): string
    {
        $grade = $question->getSubject()->getGrade()->getNumber();
        $topic = $question->getTopic();
        $questionText = $question->getQuestion();
        $answer = $question->getAnswer();
        $explanation = $question->getExplanation();
        $context = $question->getContext();

        // Add image URLs if they exist
        $imageUrls = [];
        if ($question->getImagePath()) {
            $imageUrls[] = 'https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=' . $question->getImagePath();
        }
        if ($question->getQuestionImagePath()) {
            $imageUrls[] = 'https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=' . $question->getQuestionImagePath();
        }

        $imageUrlsText = !empty($imageUrls) ? "\nImage URLs:\n" . implode("\n", $imageUrls) : '';

        return <<<PROMPT
Given a math question, break it down into multiple logical steps. Each step should be educational and interactive.

For each step, return:
- step_number: number
- type: "formula" (choose the correct formula), "value" (select correct value), or "step-fill" (fill in missing part of a LaTeX expression)
- prompt: a short instruction for the learner
- expression: LaTeX expression for the step with one blank (e.g. `[ ? ]`) or `null` if type is "formula"
- options: exactly 3 multiple choice answers, including common misconceptions as distractors
- answer: the correct option
- hint: short guidance for when the learner gets it wrong
- teach: a short explanation of the concept being tested
- final_expression: the fully correct version of the LaTeX expression after the learner selects the right answer

⚠️ Use LaTeX for all maths. Wrap  plain English inside LaTeX expressions with `\\text{...}`. Only if its on the same line as the LaTeX expression. Example: `\\text{Simplify } \\sin(30^{\\circ})`
⚠️ DO NOT wrap LaTeX expressions in dollar signs ($). Return raw LaTeX expressions.

Wrap the result in a JSON object with:
- id: short unique string (e.g., "trig1")
- grade: the grade level
- topic: e.g. "Trigonometry - Sine Rule"
- question: the full problem statement
- steps: array of the steps above


Question: {$questionText}
Context: {$context}
Answer: {$answer}
Explanation: {$explanation}
Grade: {$grade}
Topic: {$topic}{$imageUrlsText}


Instructions:
PROMPT . implode("\n", self::PROMPT_RULES['steps']['instructions']);
    }

    private function validateSteps(array $steps): void
    {
        $requiredFields = ['id', 'grade', 'topic', 'question', 'steps'];
        foreach ($requiredFields as $field) {
            if (!isset($steps[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        if (!is_array($steps['steps'])) {
            throw new \Exception("Steps must be an array");
        }

        $stepFields = ['step_number', 'type', 'prompt', 'options', 'answer', 'hint', 'teach'];
        foreach ($steps['steps'] as $index => $step) {
            foreach ($stepFields as $field) {
                if (!isset($step[$field])) {
                    throw new \Exception("Missing required field in step $index: $field");
                }
            }
        }
    }

    private function formatStepsLatex(array $steps): array
    {
        // Format the question
        $steps['question'] = $this->formatLatex($steps['question']);

        // Format each step
        foreach ($steps['steps'] as &$step) {
            $step['prompt'] = $this->formatLatex($step['prompt']);
            if (isset($step['expression'])) {
                $step['expression'] = $this->formatLatex($step['expression']);
            }
            if (isset($step['final_expression'])) {
                $step['final_expression'] = $this->formatLatex($step['final_expression']);
            }
            $step['hint'] = $this->formatLatex($step['hint']);
            $step['teach'] = $this->formatLatex($step['teach']);

            // Format options - handle both string and array options
            foreach ($step['options'] as &$option) {
                if (is_array($option)) {
                    foreach ($option as &$subOption) {
                        $subOption = $this->formatLatex($subOption);
                    }
                } else {
                    $option = $this->formatLatex($option);
                }
            }

            // Format answer - handle both string and array answers
            if (is_array($step['answer'])) {
                foreach ($step['answer'] as &$subAnswer) {
                    $subAnswer = $this->formatLatex($subAnswer);
                }
            } else {
                $step['answer'] = $this->formatLatex($step['answer']);
            }
        }

        return $steps;
    }

    private function formatLatex(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Remove any dollar sign wrapping
        $text = preg_replace('/^\$|\$$/', '', $text);

        // Replace any lowercase letter followed by '\\%' with the same letter followed by '%'
        $text = preg_replace('/([a-z])\\\\%/', '$1%', $text);

        // Check for actual LaTeX commands or mathematical expressions
        $latexIndicators = ['\\frac', '\\sum', '\\int', '\\sqrt', '\\begin', '\\end', '^', '_', '{', '}', '\\', '\left', '\right'];
        $containsLatex = false;

        foreach ($latexIndicators as $indicator) {
            if (strpos($text, $indicator) !== false) {
                $containsLatex = true;
                break;
            }
        }

        // If no LaTeX indicators found, return as is
        if (!$containsLatex) {
            return $text;
        }

        // First handle any standalone \text commands
        $text = preg_replace('/\\\\text/', '\\text', $text);

        // Handle newlines - preserve \newline commands and convert AI's double backslashes
        $text = preg_replace('/\\\\newline\s*/', '\\newline ', $text);
        $text = preg_replace('/\n\s*ewline/', '\\newline', $text);
        $text = preg_replace('/\s*\\\\\s*\\\\\s*/', ' \\newline ', $text);

        // Fix any broken LaTeX commands
        $text = str_replace(' eq ', ' \\neq ', $text);
        $text = preg_replace('/\\\\(text\{[^}]+\})\s*\\\\text/', '\\$1', $text);

        // Clean up extra spaces and fix text formatting
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\\text\{\s*\\text\{([^}]+)\}\s*\}/', '\\text{$1}', $text);
        $text = preg_replace('/\\text\{\s*or\s*\}/', '\\text{ or }', $text);
        $text = preg_replace('/(?<!\{)\s+or\s+(?!\})/', ' \\text{ or } ', $text);

        // Apply final formatting
        $text = preg_replace([
            '/\\\\\(/',
            '/\\\\\),/',
            '/\\\\\)\./',
            '/\\\\\)/',
            '/\\\\\\\\/',
            '/\\[[\]]/'
        ], [
            '',
            '',
            '',
            '',
            '\\',
            '',
        ], $text);

        return $text;
    }
}