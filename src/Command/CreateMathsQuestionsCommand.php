<?php

namespace App\Command;

use App\Entity\ExamPaper;
use App\Repository\ExamPaperRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Grade;
use App\Entity\Subject;
use App\Entity\Learner;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'app:create-math-questions',
    description: 'Extracts and creates questions for each innermost child question number using OpenAI.'
)]
class CreateMathsQuestionsCommand extends Command
{
    private const PROMPT_RULES = [
        'question' => [
            'system' => 'You are a document analysis assistant. Your task is to extract specific question content from structured exam documents. Return only the requested question text in raw text format. For any mathematical expressions, wrap them in $$ delimiters. No introductions, explanations, or formatting unless specified. DO NOT use markdown code blocks or ```json in your response.',
            'instructions' => [
                '1. Do not include any other questions.',
                '2. Return the data in a json format.',
                '3. The question node must be named exactly as the question number, do not prefix with anything.',
                '4. Do not prefix the json with any text',
                '5. DO NOT use markdown code blocks or ```json in your response',
                '6. dont include more information in brackets e.g. (answers correct to TWO decimal places)',
                '7. Use \newline for line breaks in calculations. add line breaks for a mobile screen',
                '8. IMPORTANT:  Use \text{} for text in the same line as mathematical expressions e.g.: 
                    \n A and B are independent events. P(A) = \frac{1}{3}, must be \text { A and B are independent events. } P(A) = \frac{1}{3}
                    \n Calculate the value of n for which T_n = 517, must be \text { Calculate the value of n for which } T_n = 517',
            ]
        ],
        'answer' => [
            'system' => 'You are a document analysis assistant. Your task is to extract specific answer content from answer memos. Return the answer and calculations in a JSON format with two nodes: "answer" and "calculations". if content is a math fomula, then both should be in latex inline math mode. No introductions, explanations, or formatting.',
            'instructions' => [
                '1. return the english answer',
                '2. Don\'t include any other text or formatting',
                '3. Don\'t include the correct sign or marks number in brackets',
                '4. Format all mathematical expressions in LaTeX',
                '5. IMPORTANT:  Use \text{} for text within mathematical expressions e.g. A and B are independent events. P(A) = \frac{1}{3}',
                '6. Include all necessary working steps',
                '7. Ensure the final answer is clearly marked',
                '8. Use \newline for line breaks in calculations. add line breaks for a mobile screen',
            ]
        ],
        'wrong_answers' => [
            'system' => 'You are a document analysis assistant. Your task is to generate plausible but incorrect answers for exam questions. Return the wrong answers with descriptive keys: first_option, second_option, third_option.',
            'base_instructions' => [
                '1. Return the answers in this format:',
                '2. first_option: [first wrong answer]',
                '3. second_option: [second wrong answer]',
                '4. third_option: [third wrong answer]',
                '5. Do not include the correct answer in the response.',
                '6. If correct answer has 2 answers, make sure that the wrong answers have 2 answers as well. e.g. x=2 or x=-5',
                '7. Do not number the answers, do not return the string as json, do not add new line to the string',
                '8. IMPORTANT:  Use \text{} for text within mathematical expressions e.g. A and B are independent events. P(A) = \frac{1}{3}',
                '9. Use \newline for line breaks in calculations. add line breaks for a mobile screen',
            ],
            'latex_instructions' => 'IMPORTANT: Format all mathematical expressions in LaTeX using $$ delimiters. Use \\text{} for text within mathematical expressions.',
            'non_latex_instructions' => 'IMPORTANT: Do not use LaTeX formatting. Return plain text answers.'
        ]
    ];

    public function __construct(
        private ExamPaperRepository $examPaperRepository,
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('question-number', InputArgument::OPTIONAL, 'Filter questions by number (e.g., 1.4.2)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = $this->params->get('openai_api_key');
        $apiUrl = 'https://api.openai.com/v1/chat/completions';
        $questionNumberFilter = $input->getArgument('question-number');
        $maxRetries = 1; // Maximum number of retries for failed questions

        // Check for papers in progress
        $papersInProgress = $this->examPaperRepository->findBy(['status' => ['in_progress']]);
        if (!empty($papersInProgress)) {
            $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
            $output->writeln("[$timestamp] Found papers in progress. Exiting to prevent concurrent processing.");
            return Command::SUCCESS;
        }

        $papers = $this->examPaperRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('LOWER(p.subjectName) LIKE :subject')
            ->setParameter('status', 'pending')
            ->setParameter('subject', '%mathematics%')
            ->getQuery()
            ->getResult();

        if (empty($papers)) {
            $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
            $output->writeln("[$timestamp] No papers to process.");
            return Command::SUCCESS;
        }

        foreach ($papers as $paper) {
            $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
            $output->writeln("[$timestamp] Processing paper ID: {$paper->getId()}");
            if (!$paper->getPaperOpenAiFileId() || !$paper->getQuestionNumbers() || !$paper->getMemoOpenAiFileId()) {
                continue;
            }

            // Set paper status to in_progress
            $paper->setStatus('in_progress');
            $this->entityManager->persist($paper);
            $this->entityManager->flush();

            $questionNumbers = $paper->getQuestionNumbers();
            $leafQuestions = $this->getLeafQuestions($questionNumbers);

            foreach ($leafQuestions as $questionNumber) {
                // Check if question is already processed
                $questionProgress = $paper->getQuestionProgress();
                if (isset($questionProgress[$questionNumber]) && $questionProgress[$questionNumber]['status'] === 'Done') {
                    $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                    $output->writeln("[$timestamp] Question $questionNumber already processed, skipping...");
                    continue;
                }

                // Check if question has child questions
                $childQuestionNumber = $questionNumber . " (a)";
                if (in_array($childQuestionNumber, $questionNumbers)) {
                    $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                    $output->writeln("[$timestamp] Question $questionNumber has child questions, skipping...");
                    continue;
                }

                // Apply question number filter if provided
                if ($questionNumberFilter && $questionNumber !== $questionNumberFilter) {
                    $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                    $output->writeln("[$timestamp] Skipping question $questionNumber (does not match filter: $questionNumberFilter)");
                    continue;
                }

                $retryCount = isset($questionProgress[$questionNumber]['retryCount']) ? $questionProgress[$questionNumber]['retryCount'] : 0;
                $shouldRetry = true;

                while ($shouldRetry) {
                    try {
                        // Extract question
                        $parentNumber = $this->getParentQuestion($questionNumber);
                        $grandParentNumber = $this->getGrandParentQuestion($questionNumber);

                        $questionPrompt = [
                            [
                                'role' => 'system',
                                'content' => self::PROMPT_RULES['question']['system']
                            ],
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'file',
                                        'file' => [
                                            'file_id' => $paper->getPaperOpenAiFileId()
                                        ]
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "From the question paper, extract the full text of question $questionNumber" .
                                            ($grandParentNumber && str_contains($grandParentNumber, '.') ? ", its grandparent $grandParentNumber" : "") .
                                            (($parentNumber && str_contains($parentNumber, '.')) || (substr_count($questionNumber, '.') === 1) ? ", its parent $parentNumber" : "") . "\n do not include text for sub questions for the parent node. \n" .
                                            implode("\n", self::PROMPT_RULES['question']['instructions'])
                                    ]
                                ]
                            ]
                        ];

                        $output->writeln("Question Prompt: " . json_encode($questionPrompt));

                        // Extract answer
                        $answerPrompt = [
                            [
                                'role' => 'system',
                                'content' => self::PROMPT_RULES['answer']['system']
                            ],
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'file',
                                        'file' => [
                                            'file_id' => $paper->getMemoOpenAiFileId()
                                        ]
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "From the question answer book, extract the final answer and the working from the answer memo pdf for question $questionNumber.\n\n" .
                                            "Return in the following JSON format:\n" .
                                            "{\n" .
                                            "  \"answer\": \"final answer in latex inline math mode\",\n" .
                                            "  \"calculations\": \"all working/calculations in latex inline math mode\"\n" .
                                            "}\n\n" .
                                            "Instructions:\n" .
                                            implode("\n", self::PROMPT_RULES['answer']['instructions'])
                                    ]
                                ]
                            ]
                        ];

                        // Get question content
                        $questionData = $this->httpClient->request('POST', $apiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                'model' => 'gpt-4.1-mini',
                                'messages' => $questionPrompt
                            ]
                        ])->toArray(false);

                        // Initialize variables
                        $question = null;
                        $questionText = '';

                        if (!isset($questionData['choices'][0]['message']['content'])) {
                            throw new \Exception('Invalid response format from OpenAI API');
                        }

                        $questionContent = $questionData['choices'][0]['message']['content'];
                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] Question AI Response for $questionNumber: " . $questionContent);

                        // Remove question numbers in parentheses like (1), (5), (10)
                        $questionContent = preg_replace('/\s*\(\d+\)\s*/', '', $questionContent);
                        $questionJson = json_decode($questionContent, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new \Exception('Failed to parse question JSON: ' . json_last_error_msg() . "\nContent: " . $questionContent);
                        }

                        // Validate parent question data
                        if (!isset($questionJson[$parentNumber]) && str_contains($parentNumber, '.')) {
                            throw new \Exception("Missing or invalid parent question data for $parentNumber");
                        }

                        $parentData = isset($questionJson[$parentNumber]) ? $questionJson[$parentNumber] : '';
                        if (!is_string($parentData)) {
                            throw new \Exception("Invalid parent question data format for $parentNumber");
                        }

                        $questionText = $parentData;

                        // Validate child question data
                        if (!isset($questionJson[$questionNumber])) {
                            // Try without space if not found
                            $questionNumberNoSpace = str_replace(' (', '(', $questionNumber);
                            if (!isset($questionJson[$questionNumberNoSpace])) {
                                throw new \Exception("Missing or invalid question data for $questionNumber");
                            }
                            $questionData = $questionJson[$questionNumberNoSpace];
                        } else {
                            $questionData = $questionJson[$questionNumber];
                        }

                        if (!is_string($questionData)) {
                            throw new \Exception("Invalid question data format for $questionNumber");
                        }

                        $questionText = $questionData;

                        // Format LaTeX in question text
                        $questionText = $this->formatLatex($questionText, $output);

                        //check if is latex and also does not have newlines and also very long
                        $questionText = $this->formatLongLatexForMobile($questionText, $output);

                        // Create question entity if not already created
                        if (!$question) {
                            $question = new Question();
                            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['id' => $paper->getUser()]);
                            //default values
                            $question->setCapturer($learner);
                            $question->setReviewer($learner);
                            $question->setCurriculum('CAPS');
                            $question->setRelatedQuestionIds([]);
                            $question->setTopic(null);
                            $question->setAi(true);
                        }

                        //REMOVE TWO, THREE, FOUR FROM THE QUESTION TEXT
                        $questionText = str_replace('TWO', '', $questionText);
                        $questionText = str_replace('THREE', '', $questionText);
                        $questionText = str_replace('FOUR', '', $questionText);
                        $questionText = str_replace('FIVE', '', $questionText);
                        $questionText = str_replace('SIX', '', $questionText);

                        $question->setQuestion($questionText);

                        // Initialize image variables
                        $imagePath = null;
                        $questionImagePath = null;

                        if ($paper->getImages()) {
                            $images = $paper->getImages();
                            if (!is_array($images)) {
                                $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                                $output->writeln("[$timestamp] Warning: Invalid images data format for paper ID: {$paper->getId()}");
                            } else {
                                // Check current question number

                                if (isset($images[$questionNumber])) {
                                    $questionImagePath = $images[$questionNumber];
                                }
                                // Check parent question number
                                if (isset($images[$parentNumber])) {
                                    $imagePath = $images[$parentNumber];
                                }
                                // Check grandparent question number
                                if ($grandParentNumber && isset($images[$grandParentNumber])) {
                                    $imagePath = $images[$grandParentNumber];
                                }
                            }
                        }

                        if ($imagePath) {
                            $question->setImagePath($imagePath);
                        }

                        if ($questionImagePath) {
                            $question->setQuestionImagePath($questionImagePath);
                        }

                        // Get answer content
                        $answerResponse = $this->httpClient->request('POST', $apiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                'model' => 'gpt-4.1-mini',
                                'messages' => $answerPrompt
                            ]
                        ]);

                        $answerData = $answerResponse->toArray(false);

                        if (!isset($answerData['choices'][0]['message']['content'])) {
                            throw new \Exception('Invalid response format from OpenAI API for answer');
                        }

                        $answerContent = $answerData['choices'][0]['message']['content'];
                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] Answer AI Response for $questionNumber: " . $answerContent);

                        $answerJson = json_decode($answerContent, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new \Exception('Failed to parse answer JSON: ' . json_last_error_msg() . "\nContent: " . $answerContent);
                        }

                        // Format LaTeX in answer content and calculations
                        $answerContent = $this->formatLatex($answerJson['answer'] ?? '', $output);
                        $calculations = $this->formatLatex($answerJson['calculations'] ?? '', $output);

                        // Format long LaTeX in answer content
                        $answerContent = $this->formatLongLatexForMobile($answerContent, $output);
                        $calculations = $this->formatLongLatexForMobile($calculations, $output);

                        $question->setAnswer($answerContent);
                        $question->setExplanation($calculations);

                        // Check if answer is in LaTeX format
                        $isCorrectAnswerLatex = $this->maybeWrapWithLatex($answerContent) !== $answerContent;
                        // Get wrong answer options
                        $promptContent = "question: {$questionText}. Correct Answer: \"{$answerContent}\". \n Give me exactly 3 wrong answers for this question. \n length of each answer must be similar to the length of the correct answer.";

                        $promptContent .= " \n I am setting up a mock test. \n" . implode("\n", self::PROMPT_RULES['wrong_answers']['base_instructions']);

                        if ($isCorrectAnswerLatex) {
                            $promptContent .= "\n" . self::PROMPT_RULES['wrong_answers']['latex_instructions'];
                        } else {
                            $promptContent .= "\n" . self::PROMPT_RULES['wrong_answers']['non_latex_instructions'];
                        }

                        $wrongAnswersPrompt = [
                            [
                                'role' => 'system',
                                'content' => self::PROMPT_RULES['wrong_answers']['system']
                            ],
                            [
                                'role' => 'user',
                                'content' => $promptContent
                            ]
                        ];

                        $wrongAnswersResponse = $this->httpClient->request('POST', $apiUrl, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                'model' => 'gpt-4.1-mini',
                                'messages' => $wrongAnswersPrompt
                            ]
                        ]);

                        $wrongAnswersData = $wrongAnswersResponse->toArray(false);

                        if (!isset($wrongAnswersData['choices'][0]['message']['content'])) {
                            throw new \Exception('Invalid response format from OpenAI API for wrong answers');
                        }

                        $wrongAnswersContent = $wrongAnswersData['choices'][0]['message']['content'];
                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] Wrong Answers AI Response for $questionNumber: " . $wrongAnswersContent);

                        $formattedOptions = $this->parseWrongAnswers($wrongAnswersContent, $answerContent);
                        $question->setOptions($formattedOptions);
                        $question->setType('multiple_choice');

                        // Set context based on whether grandparent exists
                        $context = '';
                        if ($grandParentNumber && isset($questionJson[$grandParentNumber])) {
                            $grandParentText = $questionJson[$grandParentNumber];
                            // Only limit text if there's an image
                            if ($imagePath && strlen($grandParentText) > 100) {
                                $grandParentText = substr($grandParentText, 0, 100) . "...";
                            }
                            $context = $grandParentText;
                        }

                        if ($parentNumber && isset($questionJson[$parentNumber])) {
                            $parentText = $questionJson[$parentNumber];
                            // Only limit text if there's an image
                            if ($imagePath && strlen($parentText) > 100) {
                                $parentText = substr($parentText, 0, 100) . "...";
                            }
                            $context = $context . "\n\n" . $parentText;
                        }

                        // Clean up context text
                        if ($context) {
                            $context = str_replace('TWO', '', $context);
                            $context = str_replace('THREE', '', $context);
                            $context = str_replace('FOUR', '', $context);
                            $context = str_replace('FIVE', '', $context);
                            $context = str_replace('SIX', '', $context);
                            // Format LaTeX in context
                            $context = $this->formatLatex($context, $output);
                            $context = $this->formatLongLatexForMobile($context, $output);
                        }

                        //remove all text after 'next to the question numbers', including 'next to the question numbers'
                        $context = preg_replace('/next to the question numbers.*$/', '', $context);
                        $question->setContext($context);

                        $question->setYear($paper->getYear());
                        $question->setTerm($paper->getTerm());
                        $question->setComment("new");
                        $question->setCreated(new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')));
                        $question->setUpdated(new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')));
                        $question->setReviewedAt(new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')));
                        $question->setQuestionNumber($questionNumber);

                        // Find the subject by name and grade
                        $grade = $this->entityManager->getRepository(Grade::class)->findOneBy(['number' => $paper->getGrade()]);
                        if (!$grade) {
                            throw new \Exception("Grade {$paper->getGrade()} not found");
                        }

                        $subject = $this->entityManager->getRepository(Subject::class)->findOneBy([
                            'name' => $paper->getSubjectName(),
                            'grade' => $grade
                        ]);

                        if (!$subject) {
                            throw new \Exception("Subject {$paper->getSubjectName()} not found for grade {$paper->getGrade()}");
                        }

                        $question->setSubject($subject);
                        $question->setStatus('new');
                        $question->setActive(true);

                        // Check for duplicate question
                        $existingQuestion = $this->entityManager->getRepository(Question::class)->findOneBy([
                            'subject' => $subject,
                            'question' => $questionText,
                            'year' => $paper->getYear(),
                            'term' => $paper->getTerm()
                        ]);

                        if ($existingQuestion) {
                            $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                            $output->writeln("[$timestamp] Duplicate question found with ID: " . $existingQuestion->getId() . ". Skipping creation.");
                            // Update progress for this question
                            $paper->updateQuestionProgress($questionNumber, "Skipped", "Duplicate question found");
                            $this->entityManager->persist($paper);
                            $this->entityManager->flush();
                            continue;
                        }

                        $this->entityManager->persist($question);
                        $this->entityManager->flush();

                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] Question created with ID: " . $question->getId());

                        // Update progress for this question
                        $paper->updateQuestionProgress($questionNumber, "Done");
                        $this->entityManager->persist($paper);
                        $this->entityManager->flush();

                        // If we get here, the question was processed successfully
                        $shouldRetry = false;
                        break;

                    } catch (\Exception $e) {
                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] <error>Error processing question $questionNumber: " . $e->getMessage() . "</error>");

                        if ($retryCount < $maxRetries) {
                            $retryCount++;
                            $output->writeln("[$timestamp] Retrying question $questionNumber (Attempt $retryCount of $maxRetries)");

                            // Update progress with retry status
                            $paper->updateQuestionProgress($questionNumber, "Retrying", "Attempt $retryCount of $maxRetries: " . $e->getMessage(), $retryCount);
                            $this->entityManager->persist($paper);
                            $this->entityManager->flush();

                            // Sleep for a short duration before retrying (exponential backoff)
                            $sleepTime = pow(2, $retryCount) * 1000000; // Convert to microseconds
                            usleep($sleepTime);

                            // Continue the while loop to retry
                            continue;
                        } else {
                            $output->writeln("[$timestamp] <error>Max retries reached for question $questionNumber. Marking as failed.</error>");
                            // Update progress with final failure status
                            $paper->updateQuestionProgress($questionNumber, "Failed", "Max retries reached: " . $e->getMessage(), $retryCount);
                            $this->entityManager->persist($paper);
                            $this->entityManager->flush();
                            $allQuestionsProcessed = false;
                            $shouldRetry = false;
                            break;
                        }
                    }
                }
            }

            // Set paper status to done if all questions were processed successfully
            $paper->setStatus('done');
            $this->entityManager->persist($paper);
            $this->entityManager->flush();
            $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
            $output->writeln("[$timestamp] Paper ID: {$paper->getId()} completed");
        }

        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
        $output->writeln("[$timestamp] Finished processing all papers.");
        return Command::SUCCESS;
    }

    private function getLeafQuestions(array $questionNumbers): array
    {
        // A leaf question is one that is not a prefix of any other question number
        $leaves = [];
        foreach ($questionNumbers as $q) {
            $isLeaf = true;
            foreach ($questionNumbers as $other) {
                if ($q !== $other && str_starts_with($other, $q . '.')) {
                    $isLeaf = false;
                    break;
                }
            }
            if ($isLeaf) {
                $leaves[] = $q;
            }
        }
        return $leaves;
    }

    private function getParentQuestion(string $questionNumber): string
    {
        // Check if it's a lettered sub-question
        if (preg_match('/\s*\([a-z]\)\s*$/i', $questionNumber)) {
            // For lettered sub-questions, return the full number without the letter
            return preg_replace('/\s*\([a-z]\)\s*$/i', '', $questionNumber);
        }

        // For numbered sub-questions, remove the last number
        if (str_contains($questionNumber, '.')) {
            $parts = explode('.', $questionNumber);
            if (count($parts) > 1) {
                array_pop($parts);
                return implode('.', $parts);
            }
        }

        return $questionNumber;
    }

    private function getGrandParentQuestion(string $questionNumber): ?string
    {
        $parent = $this->getParentQuestion($questionNumber);
        if (str_contains($parent, '.')) {
            return $this->getParentQuestion($parent);
        }
        return null;
    }

    private function maybeWrapWithLatex(string $text): string
    {
        $latexIndicators = ['\\frac', '\\sum', '\\int', '\\sqrt', '\\begin', '\\end', '^', '_', '{', '}', '\\', '\left', '\right'];

        $containsLatex = false;
        foreach ($latexIndicators as $indicator) {
            if (strpos($text, $indicator) !== false) {
                $containsLatex = true;
                break;
            }
        }

        if ($containsLatex && !str_starts_with(trim($text), '$') && substr_count($text, '$') < 2) {
            $text = '$' . $text . '$';
        }

        return $text;
    }

    private function formatLatex(string $text, ?OutputInterface $output = null): string
    {
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

        if ($this->containsHumanWords($text)) {
            //call ai to format text
            if ($output) {
                $output->writeln("Contains human words and LaTeX: " . $text);
            }
            $text = $this->formatLatexWithAI($text);
        }

        // First handle any standalone \text commands
        $text = preg_replace('/\\\\text/', '\\text', $text);

        // Handle newlines - preserve \newline commands and convert AI's double backslashes
        $text = preg_replace('/\\\\newline\s*/', '\\newline ', $text);  // Ensure space after \newline
        $text = preg_replace('/\n\s*ewline/', '\\newline', $text);  // Fix split \newline commands
        $text = preg_replace('/\s*\\\\\s*\\\\\s*/', ' \\newline ', $text);  // Convert AI's double backslashes to \newline

        // Fix any broken LaTeX commands
        $text = str_replace(' eq ', ' \\neq ', $text);  // Fix \neq
        $text = preg_replace('/\\\\(text\{[^}]+\})\s*\\\\text/', '\\$1', $text);  // Fix double \text commands

        // Clean up extra spaces and fix text formatting
        $text = preg_replace('/\s+/', ' ', $text);  // Replace multiple spaces with single space
        $text = preg_replace('/\\text\{\s*\\text\{([^}]+)\}\s*\}/', '\\text{$1}', $text);  // Fix nested \text commands
        $text = preg_replace('/\\text\{\s*or\s*\}/', '\\text{ or }', $text);  // Fix "or" text formatting
        $text = preg_replace('/(?<!\{)\s+or\s+(?!\})/', ' \\text{ or } ', $text);  // Replace standalone 'or' with \text{ or }

        // Apply final formatting
        $text = preg_replace([
            '/\\\\\(/',
            '/\\\\\),/',
            '/\\\\\)\./',
            '/\\\\\)/',
            '/\\\\\\\\/',
            '/\\[[\]]/'
        ], [
            '$',
            '$',
            '$',
            '\\',
            '\\',
            '$',
        ], $text);

        // Wrap in single dollar signs if not already wrapped and doesn't have multiple dollar signs
        if (!str_starts_with(trim($text), '$') && substr_count($text, '$') < 2) {
            $text = '$' . $text . '$';
        }

        return $text;
    }

    public function parseWrongAnswers(string $wrongAnswersContent, string $answerContent): array
    {
        $options = [];

        // Preprocess: Replace all '\\newline' with actual newlines
        $wrongAnswersContent = str_replace('\\newline', "\n", $wrongAnswersContent);

        // First, split the content by newlines to handle multi-line input
        $lines = explode("\n", $wrongAnswersContent);
        $processedContent = '';
        foreach ($lines as $line) {
            $processedContent .= ' ' . trim($line);
        }

        // Check if correct answer is wrapped in dollar signs
        $shouldWrap = str_starts_with(trim($answerContent), '$') && str_ends_with(trim($answerContent), '$');

        // Handle both single-line and multi-line responses
        $pattern = '/(first_option|second_option|third_option):\s*(?:"?\$)?(.*?)(?:\$"?)(?=\s*(?:first_option|second_option|third_option):|$)/s';
        if (preg_match_all($pattern, $processedContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = trim($match[2]);

                // Remove any existing dollar signs and quotes
                $value = str_replace(['$', '"', "'"], '', $value);
                // Replace \text with \\text for consistency
                $value = str_replace('\text', '\\text', $value);
                // Remove \newline and any text after it (shouldn't be needed now, but keep for safety)
                $value = preg_replace('/\newline.*$/', '', $value);
                // Replace \\% with %
                $value = preg_replace('/([a-z])\\\\%/', '$1%', $value);
                // Wrap in single dollar signs only if correct answer is wrapped
                $value = $shouldWrap ? '$' . $value . '$' : $value;
                // Format for mobile if it's LaTeX
                if ($shouldWrap) {
                    $value = $this->formatLongLatexForMobile($value);
                }
                $options[] = $value;
            }
        }

        // If no matches found, try a simpler pattern for basic options
        if (empty($options)) {
            $simplePattern = '/(first_option|second_option|third_option):\s*([^\n]+?)(?=\s*(?:first_option|second_option|third_option):|$)/';
            if (preg_match_all($simplePattern, $processedContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $value = trim($match[2]);
                    // Remove any quotes
                    $value = str_replace(['"', "'"], '', $value);
                    // Remove \newline and any text after it
                    $value = preg_replace('/\newline.*$/', '', $value);
                    // Replace \\% with %
                    $value = preg_replace('/([a-z])\\\\%/', '$1%', $value);
                    // Wrap in single dollar signs only if correct answer is wrapped
                    $value = $shouldWrap ? '$' . $value . '$' : $value;
                    // Format for mobile if it's LaTeX
                    if ($shouldWrap) {
                        $value = $this->formatLongLatexForMobile($value);
                    }
                    $options[] = $value;
                }
            }
        }

        // Add the correct answer
        $correctAnswer = str_replace(['"', "'"], '', trim($answerContent));
        $correctAnswer = str_replace('\text', '\\text', $correctAnswer);
        // Remove any existing dollar signs before adding new ones
        $correctAnswer = str_replace('$', '', $correctAnswer);
        // Remove \newline and any text after it
        $correctAnswer = preg_replace('/\newline.*$/', '', $correctAnswer);
        // Replace \\% with %
        $correctAnswer = preg_replace('/([a-z])\\\\%/', '$1%', $correctAnswer);
        $correctAnswer = $shouldWrap ? '$' . $correctAnswer . '$' : $correctAnswer;
        // Format for mobile if it's LaTeX
        if ($shouldWrap) {
            $correctAnswer = $this->formatLongLatexForMobile($correctAnswer);
        }
        $options[] = $correctAnswer;

        return $options;
    }

    /**
     * Test cases for parseWrongAnswers method
     */
    private function testParseWrongAnswers(): void
    {
        $testCases = [
            [
                'input' => 'first_option: "$r = 7,8\\% $" second_option: "$r = 9,1\\% $" third_option: "$r = 6,5\\% $"',
                'correct' => '$r = 8,7\\%$',
                'expected' => ['$r = 7.8%$', '$r = 9.1%$', '$r = 6.5%$', '$r = 8.7%$']
            ],
            [
                'input' => 'first_option: "$x = 3,14$" second_option: "$x = 2,71$" third_option: "$x = 1,41$"',
                'correct' => '$x = 3,14$',
                'expected' => ['$x = 3.14$', '$x = 2.71$', '$x = 1.41$', '$x = 3.14$']
            ],
            [
                'input' => 'first_option: "$y = 5\\%$" second_option: "$y = 10\\%$" third_option: "$y = 15\\%$"',
                'correct' => '$y = 7,5\\%$',
                'expected' => ['$y = 5%$', '$y = 10%$', '$y = 15%$', '$y = 7.5%$']
            ],
            [
                'input' => 'first_option: "$z = 1,23$" second_option: "$z = 4,56$" third_option: "$z = 7,89$"',
                'correct' => '$z = 3,45$',
                'expected' => ['$z = 1.23$', '$z = 4.56$', '$z = 7.89$', '$z = 3.45$']
            ]
        ];

        foreach ($testCases as $index => $testCase) {
            $result = $this->parseWrongAnswers($testCase['input'], $testCase['correct']);
            if ($result !== $testCase['expected']) {
                throw new \Exception(sprintf(
                    "Test case %d failed.\nExpected: %s\nGot: %s",
                    $index + 1,
                    json_encode($testCase['expected']),
                    json_encode($result)
                ));
            }
        }
    }

    private function containsHumanWords(string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        // List of LaTeX command words to exclude
        $excludedWords = ['frac', 'sqrt', 'sum', 'begin', 'left', 'right'];

        // Remove all LaTeX commands and their contents
        $text = preg_replace('/\\\\[a-zA-Z]+(\{[^}]*\})?/', '', $text);
        // Remove all mathematical expressions
        $text = preg_replace('/[0-9+\-*\/\^(){}[\]_=<>!&|~]+/', '', $text);

        // Remove all special characters and keep only letters
        $text = preg_replace('/[^a-zA-Z\s]/', ' ', $text);

        // Split into words and filter out short words, words with non-letters, and excluded words
        $words = array_filter(
            explode(' ', $text),
            function ($word) use ($excludedWords) {
                return strlen($word) >= 4
                    && preg_match('/^[a-zA-Z]+$/', $word)
                    && !in_array(strtolower($word), $excludedWords);
            }
        );

        // Check for single long word (more than 4 consecutive letters)
        // Exclude the excluded words from this check
        $textWithoutExcluded = $text;
        foreach ($excludedWords as $word) {
            $textWithoutExcluded = str_replace($word, '', $textWithoutExcluded);
        }
        if (preg_match('/[a-zA-Z]{5,}/', $textWithoutExcluded)) {
            return true;
        }

        // Check for multiple words with at least 4 characters
        return count($words) >= 2;
    }

    private function formatLatexWithAI(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $apiKey = $this->params->get('openai_api_key');
        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Format ONLY the given text with proper LaTeX commands. Use \text{} for human words and preserve all mathematical expressions. Keep \newline commands and wrap the entire expression in a single pair of $ signs. Do not add any additional content or explanations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Format this text with LaTeX: " . $text
                    ]
                ]
            ]
        ])->toArray(false);

        $formatted = $response['choices'][0]['message']['content'] ?? $text;

        // Remove any extra content after the original text
        $formatted = preg_replace('/If.*$/', '', $formatted);

        // Fix any broken LaTeX delimiters
        $formatted = str_replace(['\[', '\]'], ['$', '$'], $formatted);
        $formatted = str_replace(['\(', '\)'], ['$', '$'], $formatted);

        // Ensure only one pair of dollar signs wraps the entire expression
        $formatted = preg_replace('/\$\s*(.*?)\s*\$/s', '$1', $formatted);
        if (!str_starts_with(trim($formatted), '$')) {
            $formatted = '$' . $formatted . '$';
        }

        return $formatted;
    }

    private function formatLatexWithAIForMobile(string $text, ?OutputInterface $output = null): string
    {
        if (empty($text)) {
            return '';
        }

        $apiKey = $this->params->get('openai_api_key');
        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Format the given LaTeX text for optimal display on mobile screens. Add \newline commands at logical break points to ensure the text fits well on narrow screens. Break at operators (+, -, =, etc.) and after logical groups of terms. Preserve all mathematical expressions and use \text{} for human words. Wrap the entire expression in a single pair of $ signs. Do not add any additional content or explanations.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Format this LaTeX text for mobile display: " . $text
                    ]
                ]
            ]
        ])->toArray(false);

        $formatted = $response['choices'][0]['message']['content'] ?? $text;

        // Log the AI response if output is provided
        if ($output) {
            $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
            $output->writeln("[$timestamp] AI Mobile Format Response:");
            $output->writeln("Original: " . $text);
            $output->writeln("Formatted: " . $formatted);
        }

        // Remove any extra content after the original text
        $formatted = preg_replace('/If.*$/', '', $formatted);

        // Fix any broken LaTeX delimiters
        $formatted = str_replace(['\[', '\]'], ['$', '$'], $formatted);
        $formatted = str_replace(['\(', '\)'], ['$', '$'], $formatted);

        // Ensure only one pair of dollar signs wraps the entire expression
        $formatted = preg_replace('/\$\s*(.*?)\s*\$/s', '$1', $formatted);
        if (!str_starts_with(trim($formatted), '$')) {
            $formatted = '$' . $formatted . '$';
        }

        return $formatted;
    }

    private function formatLongLatexForMobile(string $text, ?OutputInterface $output = null): string
    {
        if (
            str_starts_with(trim($text), '$') &&
            str_ends_with(trim($text), '$') &&
            !str_contains($text, '\\newline') &&
            strlen($text) > 40
        ) {
            if ($output) {
                $output->writeln("Text is LaTeX, very long, and has no newlines: " . $text);
            }
            // If it's LaTeX, very long, and has no newlines, use AI to format it with proper line breaks for mobile
            return $this->formatLatexWithAIForMobile($text, $output);
        } else {
            if ($output) {
                $output->writeln("Text is not LaTeX, very long, and has no newlines: " . $text);
            }
            return $text;
        }
    }
}