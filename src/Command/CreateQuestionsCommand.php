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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = $this->params->get('openai_api_key');
        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $papers = $this->examPaperRepository->findBy(['status' => ['in_progress', 'processed_numbers']]);
        foreach ($papers as $paper) {
            $output->writeln("Processing paper ID: {$paper->getId()}");
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
                //$output->writeln("Processing question: $questionNumber for paper ID: {$paper->getId()}");

                // Check if question is already processed
                $questionProgress = $paper->getQuestionProgress();
                if (isset($questionProgress[$questionNumber]) && $questionProgress[$questionNumber]['status'] === 'Done') {
                    $output->writeln("Question $questionNumber already processed, skipping...");
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
                                    " and its parent $parentNumber. do not include text for sub questions for the parent node. \n" .
                                    "1. Do not include any other questions. \n" .
                                    "2. Return only the raw question text. \n" .
                                    "3. If question contains points points, make sure that the alphabet (bullet point) and the text are on the same line. e.g. A. taxes\n" .
                                    "3. return the data in a json format. \n" .
                                    "4. the question node must be named exactly as the question number, do not prefix with anything. \n" .
                                    "5. do not prefix the json with any text"
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
                                'text' => "From the question answer book, extract the first correct answer from the answer memo pdf for question $questionNumber \n do not prefix the answer. just return the answer as is. dont introduce the answer or comment on the answer \n do not include the correct sign or marks number in brackets"
                            ]
                        ]
                    ]
                ];

                try {
                    // Get question content
                    $output->writeln("Question Prompt: " . json_encode($questionPrompt));
                    $questionResponse = $this->httpClient->request('POST', $apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-4.1',
                            'messages' => $questionPrompt
                        ]
                    ]);

                    $questionData = $questionResponse->toArray(false);
                    $output->writeln("Question Data: " . json_encode($questionData));

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

                    $output->writeln("Question ($questionNumber):");

                    $output->writeln("Parent Question ($parentNumber):");

                    // Validate parent question data
                    if (!isset($questionJson[$parentNumber])) {
                        throw new \Exception("Missing or invalid parent question data for $parentNumber");
                    }

                    $parentData = $questionJson[$parentNumber];
                    if (!is_string($parentData)) {
                        $output->writeln("Parent data: " . json_encode($parentData));
                        throw new \Exception("Invalid parent question data format for $parentNumber");
                    }

                    $questionText = $parentData;
                    $output->writeln("Context: " . $questionText);
                    $output->writeln("Has Image/Table/Diagram: " . (isset($parentData['has_image_or_table_or_diagram']) && $parentData['has_image_or_table_or_diagram'] ? 'Yes' : 'No'));

                    $output->writeln("\nQuestion ($questionNumber):");

                    // Validate child question data
                    if (!isset($questionJson[$questionNumber])) {
                        throw new \Exception("Missing or invalid question data for $questionNumber");
                    }

                    $questionData = $questionJson[$questionNumber];
                    if (!is_string($questionData)) {
                        throw new \Exception("Invalid question data format for $questionNumber");
                    }

                    $questionText = $questionData;
                    $output->writeln("Question: " . $questionText);

                    // Get answer content
                    $answerResponse = $this->httpClient->request('POST', $apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-4.1',
                            'messages' => $answerPrompt
                        ]
                    ]);

                    $answerData = $answerResponse->toArray(false);

                    if (!isset($answerData['choices'][0]['message']['content'])) {
                        throw new \Exception('Invalid response format from OpenAI API for answer');
                    }

                    $answerContent = $answerData['choices'][0]['message']['content'];
                    $output->writeln("\nAnswer content for $questionNumber:");
                    $output->writeln($answerContent);

                    // Create question entity
                    $question = new Question();

                    $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['email' => 'nkosi@gmail.com']);
                    //default values
                    $question->setCapturer($learner);
                    $question->setReviewer($learner);
                    $question->setCurriculum('CAPS');
                    $question->setRelatedQuestionIds([]);
                    $question->setTopic(null);

                    $question->setQuestion($questionText);
                    $question->setAnswer($answerContent);

                    // Process images if present
                    if ($paper->getImages()) {
                        $images = $paper->getImages();
                        if (!is_array($images)) {
                            $output->writeln("Warning: Invalid images data format for paper ID: {$paper->getId()}");
                            continue;
                        }

                        // Check for images in parent, grandparent, and current question
                        $imagePath = null;
                        $questionImagePath = null;

                        // Check current question number
                        if (isset($images[$questionNumber])) {
                            $questionImagePath = $images[$questionNumber];
                        }
                        // Check parent question number
                        if (isset($images[$parentNumber])) {
                            $imagePath = $images[$parentNumber];
                        }
                        // Check grandparent question number
                        elseif ($grandParentNumber && isset($images[$grandParentNumber])) {
                            $imagePath = $images[$grandParentNumber];
                        }

                        if ($imagePath) {
                            $question->setImagePath($imagePath);
                            $output->writeln("Found image for question: " . $imagePath);
                        } else {
                            $output->writeln("No matching image found for question parents $parentNumber, grandparent $grandParentNumber" .
                                ($grandParentNumber ? ", or grandparent $grandParentNumber" : ""));
                        }

                        if ($questionImagePath) {
                            $question->setQuestionImagePath($questionImagePath);
                            $output->writeln("Found image for question: " . $questionImagePath);
                        } else {
                            $output->writeln("No matching image found for question $questionNumber");
                        }
                    }

                    // Get wrong answer options
                    $wrongAnswersPrompt = [
                        [
                            'role' => 'system',
                            'content' => 'You are a document analysis assistant. Your task is to generate plausible but incorrect answers for exam questions.'
                        ],
                        [
                            'role' => 'user',
                            'content' => "question: {$questionText}. Correct Answer: \"{$answerContent}\". \n Give me exactly 3 wrong answers for this question. \n length of each answer must be similar to the length of the correct answer. \n if a letter is provided as the answer, then only letters must be in the options \n I am setting up a mock test. \n separate the answers by an underscore sign, do not number the answers, do not return the string as json, do not add new line to the string "
                        ]
                    ];

                    $wrongAnswersResponse = $this->httpClient->request('POST', $apiUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-4.1',
                            'messages' => $wrongAnswersPrompt
                        ]
                    ]);

                    $wrongAnswersData = $wrongAnswersResponse->toArray(false);

                    if (!isset($wrongAnswersData['choices'][0]['message']['content'])) {
                        throw new \Exception('Invalid response format from OpenAI API for wrong answers');
                    }

                    $wrongAnswersContent = $wrongAnswersData['choices'][0]['message']['content'];
                    $output->writeln("\nWrong answer options for $questionNumber:");
                    $output->writeln($wrongAnswersContent);

                    // Format options into the required structure
                    $optionsArray = explode('_', $wrongAnswersContent);
                    $formattedOptions = [
                        'option1' => $optionsArray[0] ?? '',
                        'option2' => $optionsArray[1] ?? '',
                        'option3' => $optionsArray[2] ?? '',
                        'option4' => $answerContent ?? ''
                    ];
                    $question->setOptions($formattedOptions);
                    $question->setType('multiple_choice');

                    // Set context based on whether grandparent exists
                    $context = '';
                    if ($grandParentNumber && isset($questionJson[$grandParentNumber])) {
                        $grandParentText = $questionJson[$grandParentNumber];
                        $parentText = $questionJson[$parentNumber];
                        $context = $grandParentText . "\n" . $parentText;
                    } else {
                        $context = $questionJson[$parentNumber];
                    }
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
                        $output->writeln("Duplicate question found with ID: " . $existingQuestion->getId() . ". Skipping creation.");
                        // Update progress for this question
                        $paper->updateQuestionProgress($questionNumber, "Skipped", "Duplicate question found");
                        $this->entityManager->persist($paper);
                        $this->entityManager->flush();
                        continue;
                    }

                    $this->entityManager->persist($question);
                    $this->entityManager->flush();

                    $output->writeln("Question created with ID: " . $question->getId());
                    $output->writeln("----------------------------------------");

                    // Update progress for this question
                    $paper->updateQuestionProgress($questionNumber, "Done");
                    $this->entityManager->persist($paper);
                    $this->entityManager->flush();

                } catch (\Exception $e) {
                    $output->writeln("<error>Error processing question $questionNumber: " . $e->getMessage() . "</error>");
                    // Update progress with failure status
                    $paper->updateQuestionProgress($questionNumber, "Failed", $e->getMessage());
                    $this->entityManager->persist($paper);
                    $this->entityManager->flush();
                    $allQuestionsProcessed = false;
                    continue;
                }
            }

            // Set paper status to done if all questions were processed successfully
            if ($allQuestionsProcessed) {
                $paper->setStatus('done');
                $this->entityManager->persist($paper);
                $this->entityManager->flush();
                $output->writeln("Paper ID: {$paper->getId()} completed successfully");
            }
        }
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
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $output->writeln("Original question number: " . $questionNumber);

        // Check if it's a lettered sub-question
        if (preg_match('/\s*\([a-z]\)\s*$/i', $questionNumber)) {
            // For lettered sub-questions, return the full number without the letter
            $result = preg_replace('/\s*\([a-z]\)\s*$/i', '', $questionNumber);
            $output->writeln("Lettered sub-question, returning: " . $result);
            return $result;
        }

        // For numbered sub-questions, remove the last number
        if (str_contains($questionNumber, '.')) {
            $parts = explode('.', $questionNumber);
            $output->writeln("Parts after splitting: " . json_encode($parts));

            if (count($parts) > 1) {
                array_pop($parts);
                $result = implode('.', $parts);
                $output->writeln("Numbered sub-question, returning: " . $result);
                return $result;
            }
        }

        $output->writeln("No dots found, returning original: " . $questionNumber);
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