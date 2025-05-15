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
    name: 'app:create-questions',
    description: 'Extracts and creates questions for each innermost child question number using OpenAI.'
)]
class CreateQuestionsCommand extends Command
{
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

        while (true) {
            $papers = $this->examPaperRepository->findBy(['status' => ['pending']]);

            if (empty($papers)) {
                $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                $output->writeln("[$timestamp] No papers to process. Waiting for 5 minutes...");
                sleep(300); // Sleep for 5 minutes
                continue;
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
                $allQuestionsProcessed = true;

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
                    if ($questionNumberFilter && !str_contains($questionNumber, $questionNumberFilter)) {
                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] Skipping question $questionNumber (does not match filter: $questionNumberFilter)");
                        continue;
                    }

                    // Extract question
                    $parentNumber = $this->getParentQuestion($questionNumber);
                    $grandParentNumber = $this->getGrandParentQuestion($questionNumber);

                    $questionPrompt = [
                        [
                            'role' => 'system',
                            'content' => 'You are a document analysis assistant. Your task is to extract specific question content from structured exam documents. Return only the requested question text in raw text format—no introductions, explanations, or formatting unless specified.'
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
                                        ($parentNumber && str_contains($parentNumber, '.') ? ", its parent $parentNumber" : "") . "\n do not include text for sub questions for the parent node. \n" .
                                        "1. Do not include any other questions. \n" .
                                        "2. Return only the raw question text. \n" .
                                        "3. Do not include quotaiton marks in the question text. \n" .
                                        "3. do not include any text in tables or diagrams or images and pictures. \n" .
                                        "4. if question is a match table question, then return value from column A only. \n" .
                                        "4. If question contains points points, make sure that the alphabet (bullet point) and the text are on the same line. add a new line before each pint e.g. A. taxes\n" .
                                        "5. return the data in a json format. \n" .
                                        "6. the question node must be named exactly as the question number, do not prefix with anything. \n" .
                                        "7. do not prefix the json with any text"
                                ]
                            ]
                        ]
                    ];

                    // Extract answer
                    $answerPrompt = [
                        [
                            'role' => 'system',
                            'content' => 'You are a document analysis assistant. Your task is to extract specific answer content from answer memos. Return only the raw answer text—no introductions, explanations, or formatting.'
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
                                    'text' => "From the question answer book, extract the first correct answer from the answer memo pdf for question $questionNumber \n do not prefix the answer. \n just return the answer as is. \n if answer contains multiple lines, return the first line only. \n dont introduce the answer or comment on the answer \n do not include the correct sign or marks number in brackets"
                                ]
                            ]
                        ]
                    ];

                    try {
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
                        $isMatchTableQuestion = false;
                        $questionText = '';

                        //check if working with a match table question
                        if (
                            isset($questionData['choices'][0]['message']['content']) &&
                            stripos($questionData['choices'][0]['message']['content'], 'Match') !== false &&
                            stripos($questionData['choices'][0]['message']['content'], 'Column') !== false
                        ) {
                            $isMatchTableQuestion = true;

                            // Create a new prompt to extract column values
                            $matchColumnsPrompt = [
                                [
                                    'role' => 'system',
                                    'content' => 'You are a document analysis assistant. Your task is to extract column values from match table questions and return them in a structured JSON format.'
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
                                            'text' => "From the question paper, extract the values from Column A and Column B for question $questionNumber. Return the data in the following JSON format:\n" .
                                                "{\n" .
                                                "  \"columnA\": [\"value1\", \"value2\", ...],\n" .
                                                "  \"columnB\": [\"value1\", \"value2\", ...]\n" .
                                                "}\n" .
                                                "1. include the columnB alphabet for each option\n" .
                                                "2. Return only the JSON, no additional text\n" .
                                                "3. Make sure all the values in columnB are included in the json\n" .
                                                "3. Do not change the order of the values in column B\n"
                                        ]
                                    ]
                                ]
                            ];

                            $matchColumnsResponse = $this->httpClient->request('POST', $apiUrl, [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $apiKey,
                                    'Content-Type' => 'application/json',
                                ],
                                'json' => [
                                    'model' => 'gpt-4.1-mini',
                                    'messages' => $matchColumnsPrompt
                                ]
                            ]);

                            $matchColumnsData = $matchColumnsResponse->toArray(false);

                            // Append Column B content to question text
                            if (isset($matchColumnsData['choices'][0]['message']['content'])) {
                                $columnsJson = json_decode($matchColumnsData['choices'][0]['message']['content'], true);
                                if (isset($columnsJson['columnB']) && is_array($columnsJson['columnB'])) {
                                    $questionText = $questionData['choices'][0]['message']['content'] . "\n\n" . implode("\n", $columnsJson['columnB']);
                                }
                            }
                        }

                        if (!isset($questionData['choices'][0]['message']['content'])) {
                            throw new \Exception('Invalid response format from OpenAI API');
                        }

                        $questionContent = $questionData['choices'][0]['message']['content'];
                        // Remove question numbers in parentheses like (1), (5), (10)
                        $questionContent = preg_replace('/\s*\(\d+\)\s*/', '', $questionContent);
                        $questionJson = json_decode($questionContent, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new \Exception('Failed to parse question JSON: ' . json_last_error_msg());
                        }

                        // Check if this is a true/false question
                        $isTrueFalseQuestion = false;
                        if (isset($questionData['choices'][0]['message']['content'])) {
                            $content = strtoupper($questionData['choices'][0]['message']['content']);
                            if (strpos($content, 'TRUE') !== false && strpos($content, 'FALSE') !== false) {
                                $isTrueFalseQuestion = true;

                                // Update answer prompt for true/false questions
                                $answerPrompt = [
                                    [
                                        'role' => 'system',
                                        'content' => 'You are a document analysis assistant. Your task is to extract the correct answer (TRUE or FALSE) from answer memos. Return only TRUE or FALSE.'
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
                                                'text' => "From the question answer book, extract the correct answer (TRUE or FALSE) for question $questionNumber. Return only TRUE or FALSE, nothing else."
                                            ]
                                        ]
                                    ]
                                ];
                            }
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

                        // Create question entity if not already created for match table
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

                        // For match table questions, clean up the question text and ensure column B values are included
                        if ($isMatchTableQuestion) {
                            // Remove any answer that might be included in the question text (format: "X. answer text")
                            $questionText = preg_replace('/\n[A-Z]\.\s.*$/', '', $questionText);

                            if (isset($matchColumnsData['choices'][0]['message']['content'])) {
                                $content = $matchColumnsData['choices'][0]['message']['content'];
                                // Remove markdown code block markers if present
                                $content = preg_replace('/^```json\s*|\s*```$/', '', $content);
                                $columnsJson = json_decode($content, true);
                                if (isset($columnsJson['columnB']) && is_array($columnsJson['columnB'])) {
                                    $questionText .= "\n\n" . implode("\n", $columnsJson['columnB']);
                                }
                            }
                        }

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
                                $output->writeln("Images: " . json_encode($images));

                                if (isset($images[$questionNumber])) {
                                    $output->writeln("[$timestamp] Image found for question number: {$questionNumber}");
                                    $questionImagePath = $images[$questionNumber];
                                } else {
                                    $output->writeln("[$timestamp] Warning: No image found for question number: {$questionNumber}");
                                }
                                // Check parent question number
                                if (isset($images[$parentNumber])) {
                                    $output->writeln("[$timestamp] Image found for parent question number: {$parentNumber}");
                                    $imagePath = $images[$parentNumber];
                                } else {
                                    $output->writeln("[$timestamp] Warning: No image found for parent question number: {$parentNumber}");
                                }
                                // Check grandparent question number
                                if ($grandParentNumber && isset($images[$grandParentNumber])) {
                                    $output->writeln("[$timestamp] Image found for grandparent question number: {$grandParentNumber}");
                                    $imagePath = $images[$grandParentNumber];
                                } else {
                                    $output->writeln("[$timestamp] Warning: No image found for grandparent question number: {$grandParentNumber}");
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

                        $question->setAnswer($answerContent);

                        // Check if answer is a single letter A, B, C, or D
                        if (in_array(trim($answerContent), ['A', 'B', 'C', 'D'])) {
                            $wrongAnswersContent = implode('_', array_filter(['A', 'B', 'C', 'D'], function ($letter) use ($answerContent) {
                                return $letter !== trim($answerContent);
                            }));
                        } else {
                            // Get wrong answer options
                            if (!$isMatchTableQuestion) {
                                $promptContent = "question: {$questionText}. Correct Answer: \"{$answerContent}\". \n Give me exactly 3 wrong answers for this question. \n length of each answer must be similar to the length of the correct answer. \n if a letter is provided as the answer, then only letters must be in the options";

                                // Check if answer contains forward slashes
                                if (strpos($answerContent, '/') !== false) {
                                    $promptContent .= " \n all options must contain forward slashes in the same format as the correct answer";
                                }

                                $promptContent .= " \n I am setting up a mock test. \n separate the answers by an underscore sign, do not number the answers, do not return the string as json, do not add new line to the string";

                                $wrongAnswersPrompt = [
                                    [
                                        'role' => 'system',
                                        'content' => 'You are a document analysis assistant. Your task is to generate plausible but incorrect answers for exam questions.'
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
                            } else {
                                $wrongAnswersContent = "A_B_C_D";
                            }
                        }

                        // Format options into the required structure
                        if ($isTrueFalseQuestion) {
                            $formattedOptions = [
                                'option1' => 'TRUE',
                                'option2' => 'FALSE',
                                'option3' => 'MAYBE',
                                'option4' => 'SOMETIMES'
                            ];
                        } else {
                            $optionsArray = explode('_', $wrongAnswersContent);
                            // Remove the correct answer (D) from optionsArray if it exists
                            $optionsArray = array_filter($optionsArray, function ($option) use ($answerContent) {
                                return $option !== $answerContent;
                            });
                            // Remove quotation marks from each option
                            $optionsArray = array_map(function ($option) {
                                return str_replace(['"', "'"], '', trim($option));
                            }, $optionsArray);
                            $formattedOptions = [
                                'option1' => $optionsArray[0] ?? '',
                                'option2' => $optionsArray[1] ?? '',
                                'option3' => $optionsArray[2] ?? '',
                                'option4' => str_replace(['"', "'"], '', trim($answerContent)) ?? ''
                            ];
                        }
                        $question->setOptions($formattedOptions);
                        $question->setType($isTrueFalseQuestion ? 'true_false' : 'multiple_choice');

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
                        }

                        //remove all text after 'next to the question numbers', including 'next to the question numbers'
                        $context = preg_replace('/next to the question numbers.*$/', '', $context);
                        $question->setContext($context);

                        $question->setYear($paper->getYear());
                        $question->setTerm($paper->getTerm());
                        $question->setComment("new");
                        $question->setCreated(new \DateTime());
                        $question->setUpdated(new \DateTime());
                        $question->setReviewedAt(new \DateTime());

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

                    } catch (\Exception $e) {
                        $timestamp = (new \DateTime('now', new \DateTimeZone('Africa/Johannesburg')))->format('Y-m-d H:i:s');
                        $output->writeln("[$timestamp] <error>Error processing question $questionNumber: " . $e->getMessage() . "</error>");
                        // Update progress with failure status
                        $paper->updateQuestionProgress($questionNumber, "Failed", $e->getMessage());
                        $this->entityManager->persist($paper);
                        $this->entityManager->flush();
                        $allQuestionsProcessed = false;
                        continue;
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
            $output->writeln("[$timestamp] Finished processing current batch. Waiting for 5 minutes before checking for new papers...");
            sleep(300); // Sleep for 5 minutes
        }
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
}