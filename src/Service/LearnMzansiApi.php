<?php

namespace App\Service;

use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Environment\Console;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Learner;
use App\Entity\Question;
use App\Entity\Result;
use App\Entity\Subject;
use App\Entity\Issue;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use App\Entity\Subscription;

class LearnMzansiApi extends AbstractController
{
    private $em;
    private $logger;
    private $projectDir;
    private $openAiKey;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        string $projectDir,
        string $openAiKey
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
        $this->openAiKey = $openAiKey;
    }


    public function getLearner(Request $request)
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {

            $uid = $request->query->get('uid');

            if (empty($uid)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'UID values are required'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            return $learner;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting learner'
            );
        }
    }

    /**
     * Get question for the ID.
     *
     * @return array
     */
    public function getQuestionById(Request $request): array
    {

        $id = $request->query->get('id');

        if (empty($id)) {
            return array(
                'status' => 'NOK',
                'message' => 'Question id is required'
            );
        }

        $query = $this->em->createQuery(
            'SELECT q
            FROM App\Entity\Question q
            WHERE q.id = :id'
        )->setParameter('id', $id);

        return $query->getResult();
    }


    /**
     * Create a new question from JSON request data.
     *
     * @param array $data The JSON request body as an associative array.
     * @return Question|null The created question or null on failure.
     */
    public function createQuestion(array $data, Request $request)
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            // if ($adminCheck['status'] === 'NOK') {
            //     return $adminCheck;
            // }

            $userId = $data['uid'] ?? null;

            $user = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $userId]);
            if (!$user) {
                return array(
                    'status' => 'NOK',
                    'message' => 'User not found'
                );
            }

            $questionId = $data['question_id'] ?? null;

            // Validate required fields
            if (empty($data['type']) || empty($data['subject']) || empty($data['year']) || empty($data['term']) || empty($data['answer']) || empty($data['curriculum'])) {
                return array(
                    'status' => 'NOK',
                    'message' => "Missing required fields."
                );
            }

            //return an error if the capturer has more than 10 rejected questions
            $rejectedQuestions = $this->em->getRepository(Question::class)->findBy(['capturer' => $data['capturer'], 'status' => 'rejected']);
            if (count($rejectedQuestions) >= 10 && $questionId == 0) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Cannot create new question - Please fix the errors in your rejected questions'
                );
            }

            //return error if the capturer has question with type "single" or "true_false"
            $singleQuestions = $this->em->getRepository(Question::class)->findBy(['capturer' => $data['capturer'], 'type' => 'single']);
            if (count($singleQuestions) >= 1 && $questionId == 0) {
                return array(
                    'status' => 'NOK',
                    'message' => 'You have single questions to convert to multiple choice'
                );
            }

            // Check number of questions in new status not captured by this user
            $queryBuilder = $this->em->createQueryBuilder();
            $parameters = new ArrayCollection([
                new Parameter('status', 'new'),
                new Parameter('capturer', $data['capturer'])
            ]);
            $queryBuilder->select('COUNT(q.id)')
                ->from('App\Entity\Question', 'q')
                ->where('q.status = :status')
                ->andWhere('q.capturer != :capturer');

            $queryBuilder->setParameters($parameters);

            $newQuestionsCount = $queryBuilder->getQuery()->getSingleScalarResult();

            // if ($newQuestionsCount > 50) {
            //     return array(
            //         'status' => 'NOK',
            //         'message' => 'Cannot create new question - Please help review questions in the new status'
            //     );
            // }
            // Validate that options are not empty for multiple_choice or multi_select types - fixed
            if (($data['type'] == 'multiple_choice' || $data['type'] == 'multi_select')) {
                if (empty($data['options']['option1']) || empty($data['options']['option2']) || empty($data['options']['option3']) || empty($data['options']['option4'])) {
                    return array(
                        'status' => 'NOK',
                        'message' => "Options cannot be empty for multiple_choice or multi_select types."
                    );
                }
            }

            //check that the expected answer is not too long
            //spit answer by |
            $answers = explode('|', $data['answer']);
            foreach ($answers as $answer) {
                $numberOfWords = str_word_count($answer);
                if ($numberOfWords > 4 && $data['type'] == 'single') {
                    return array(
                        'status' => 'NOK',
                        'message' => "Too many words in the expected answer, use multiple choice instead."
                    );
                }
            }

            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $data['grade']]);
            // Fetch the associated Subject entity
            $subject = $this->em->getRepository(Subject::class)->findOneBy(['name' => $data['subject'], 'grade' => $grade]);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => "Subject with ID {$data['subject']} not found."
                );
            }

            // Check if a question with the same subject and question text already exists
            $existingQuestion = $this->em->getRepository(Question::class)->findOneBy([
                'subject' => $subject,
                'question' => $data['question']
            ]);

            if ($existingQuestion && $existingQuestion->getId() != $questionId) {

                return array(
                    'status' => 'NOK',
                    'message' => 'A question with the same subject and text already exists. Question ID: ' . $existingQuestion->getId()
                );
            }

            $this->logger->info("Creating new question with data: " . json_encode($data));

            // Create a new Question entity

            if ($questionId !== 0) {
                $question = $this->em->getRepository(Question::class)->find($questionId);
                if (!$question) {
                    return array(
                        'status' => 'NOK',
                        'message' => 'Question not found'
                    );
                }
            } else {
                // return array(
                //     'status' => 'NOK',
                //     'message' => "Question capturing paused, quality control is being done on the questions."
                // );
                $question = new Question();
            }

            $data['options']['option1'] = str_replace('{"answers":"', '', $data['options']['option1']);
            $data['options']['option1'] = rtrim($data['options']['option1'], '"}');

            $data['options']['option2'] = str_replace('{"answers":"', '', $data['options']['option2']);
            $data['options']['option2'] = rtrim($data['options']['option2'], '"}');


            $data['options']['option3'] = str_replace('{"answers":"', '', $data['options']['option3']);
            $data['options']['option3'] = rtrim($data['options']['option3'], '"}');


            $data['options']['option4'] = str_replace('{"answers":"', '', $data['options']['option4']);
            $data['options']['option4'] = rtrim($data['options']['option4'], '"}');


            if (!empty($data['type'])) {
                $question->setQuestion($data['question']);
            } else {
                $question->setQuestion("");
            }
            $question->setType($data['type']);
            $question->setSubject($subject);
            $question->setContext($data['context'] ?? null);
            $question->setAnswer(is_array($data['answer']) ? json_encode($data['answer']) : json_encode([$data['answer']]));
            $question->setOptions($data['options'] ?? null); // Pass the array directly
            $question->setTerm($data['term'] ?? null);
            $question->setExplanation($data['explanation'] ?? null);
            $question->setYear($data['year'] ?? null);
            $question->setCapturer($user);
            $question->setReviewer($user);
            $question->setCreated(new \DateTime());
            $question->setActive(true);
            $question->setStatus('new');
            $question->setComment("new");
            $question->setCurriculum($data['curriculum'] ?? "CAPS");

            //reset images
            $question->setImagePath('');
            $question->setQuestionImagePath('');
            $question->setAnswerImage('');

            // Persist and flush the new entity
            $this->em->persist($question);
            $this->em->flush();


            $this->logger->info("Created new question with ID {$question->getId()}.");

            if ($questionId == 0) {
                return array(
                    'status' => 'OK',
                    'message' => 'Successfully created question',
                    'question_id' => $question->getId()
                );
            } else {
                return array(
                    'status' => 'OK',
                    'message' => 'Successfully updated question',
                    'question_id' => $question->getId()
                );
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
            // Log the error or handle as needed
            error_log($e->getMessage());
            return null;
        }
    }


    function cleanOptions($options)
    {
        $cleanedOptions = [];
        foreach ($options as $key => $value) {
            // Remove the unwanted string
            $value = str_replace(['{\"answers\":\"', '\"}'], '', $value);
            $value = str_replace(['\"}'], '', $value);

            // Trim any leading or trailing whitespace
            $value = trim($value);
            $cleanedOptions[$key] = $value;
        }
        return $cleanedOptions;
    }

    public function getRandomQuestionBySubjectName(string $subjectName, string $paperName, string $uid, int $questionId)
    {
        try {
            // Get the learner first
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            // Check if learner is admin
            if ($learner->getRole() === 'admin' && $learner->getName() != 'Lethabo Mathabatha' && $learner->getName() != 'Exam Quiz' && $learner->getName() != 'Benedict Nkosi') {
                // For admin, get their captured questions with 'new' status
                $qb = $this->em->createQueryBuilder();
                $qb->select('q')
                    ->from('App\Entity\Question', 'q')
                    ->join('q.subject', 's')
                    ->where('s.name = :subjectName')
                    ->andWhere('q.active = :active')
                    ->andWhere('q.status = :status')
                    ->andWhere('q.capturer = :capturer');

                $parameters = new ArrayCollection([
                    new Parameter('subjectName', $subjectName . ' ' . $paperName),
                    new Parameter('active', true),
                    new Parameter('status', 'new'),
                    new Parameter('capturer', $learner)
                ]);

                $qb->setParameters($parameters);

                $query = $qb->getQuery();
                $questions = $query->getResult();
                if (!empty($questions)) {
                    shuffle($questions);
                    $randomQuestion = $questions[0];

                    //shuffle the options
                    $options = $randomQuestion->getOptions();
                    if ($options) {
                        shuffle($options);
                        $randomQuestion->setOptions($options);
                    }
                    return $randomQuestion;
                } else {
                    return array(
                        'status' => 'NOK',
                        'message' => 'No new questions found for review',
                        'context' => '',
                        'image_path' => ''
                    );
                }
            }

            // For non-admin learners, continue with existing logic
            // Get learner's grade
            $grade = $learner->getGrade();
            if (!$grade) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner grade not found'
                );
            }

            // Get learner's terms and curriculum as arrays from comma-delimited strings
            $learnerTerms = $learner->getTerms() ? array_map('trim', explode(',', $learner->getTerms())) : [];
            $learnerCurriculum = $learner->getCurriculum() ? array_map('trim', explode(',', $learner->getCurriculum())) : [];

            // First, get the IDs of mastered questions (answered correctly 3 times in a row)
            $masteredQuestionsQb = $this->em->createQueryBuilder();
            $masteredQuestionsQb->select('DISTINCT IDENTITY(r1.question) as questionId')
                ->from('App\Entity\Result', 'r1')
                ->join('r1.question', 'q1')
                ->where('r1.learner = :learner')
                ->andWhere('r1.outcome = :outcome')
                ->andWhere('EXISTS (
                    SELECT 1 
                    FROM App\Entity\Result r2 
                    WHERE r2.learner = r1.learner 
                    AND r2.question = r1.question 
                    AND r2.outcome = :outcome 
                    AND r2.created < r1.created 
                    AND EXISTS (
                        SELECT 1 
                        FROM App\Entity\Result r3 
                        WHERE r3.learner = r1.learner 
                        AND r3.question = r1.question 
                        AND r3.outcome = :outcome 
                        AND r3.created < r2.created
                    )
                )')
                ->setParameter('learner', $learner)
                ->setParameter('outcome', 'correct');

            $masteredQuestions = $masteredQuestionsQb->getQuery()->getResult();
            $masteredQuestionIds = array_map(function ($result) {
                return $result['questionId'];
            }, $masteredQuestions);

            // Build query with term and curriculum conditions
            $qb = $this->em->createQueryBuilder();
            $qb->select('q')
                ->from('App\Entity\Question', 'q')
                ->join('q.subject', 's')
                ->where('s.name = :subjectName')
                ->andWhere('s.grade = :grade')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status');

            // Exclude mastered questions if any exist
            if (!empty($masteredQuestionIds)) {
                $qb->andWhere('q.id NOT IN (:masteredIds)');
            }

            // Add term condition if learner has terms specified
            if (!empty($learnerTerms)) {
                $qb->andWhere('q.term IN (:terms)');
            }

            // Add curriculum condition if learner has curriculum specified
            if (!empty($learnerCurriculum)) {
                $qb->andWhere('q.curriculum IN (:curriculum)');
            }

            // Set parameters
            $parameters = new ArrayCollection([
                new Parameter('subjectName', $subjectName . ' ' . $paperName),
                new Parameter('grade', $grade),
                new Parameter('active', true),
                new Parameter('status', 'approved')
            ]);

            if (!empty($masteredQuestionIds)) {
                $parameters->add(new Parameter('masteredIds', $masteredQuestionIds));
            }

            if (!empty($learnerTerms)) {
                $parameters->add(new Parameter('terms', $learnerTerms));
            }

            if (!empty($learnerCurriculum)) {
                $parameters->add(new Parameter('curriculum', $learnerCurriculum));
            }

            $qb->setParameters($parameters);

            $query = $qb->getQuery();
            $questions = $query->getResult();
            if (!empty($questions)) {
                shuffle($questions);
                $randomQuestion = $questions[0]; // Get the first random question
            } else {
                return array(
                    'status' => 'NOK',
                    'message' => 'No more questions available',
                    'context' => '',
                    'image_path' => ''
                );
            }

            //shuffle the options
            $options = $randomQuestion->getOptions();
            if ($options) {
                shuffle($options);
                $randomQuestion->setOptions($options);
            }
            return $randomQuestion;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting random question'
            );
        }
    }

    private function cleanCommaString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // Remove quotes and trim each item
        $items = explode(',', $value);
        $cleanedItems = array_map(function ($item) {
            return trim(trim($item, '"\''));
        }, $items);
        return implode(',', $cleanedItems);
    }

    public function updateLearner(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $data = json_decode($request->getContent(), true);
            $uid = $data['uid'] ?? null;
            $isRegistration = false;

            if (!$uid) {
                return array(
                    'status' => 'NOK',
                    'message' => 'UID is required'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                $learner = new Learner();
                $learner->setUid($uid);
                $isRegistration = true;
            }

            if (isset($data['terms'])) {
                $learner->setTerms($this->cleanCommaString($data['terms']));
            }

            if (isset($data['curriculum'])) {
                $learner->setCurriculum($this->cleanCommaString($data['curriculum']));
            }

            $name = $data['name'] ?? null;
            $gradeName = $data['grade'] ?? null;
            $schoolName = $data['school_name'] ?? null;
            $schoolAddress = $data['school_address'] ?? null;
            $schoolLatitude = $data['school_latitude'] ?? null;
            $schoolLongitude = $data['school_longitude'] ?? null;
            $notificationHour = $data['notification_hour'] ?? 18;
            $terms = $data['terms'] ?? null;
            $curriculum = $data['curriculum'] ?? null;
            $email = $data['email'] ?? null;

            if (empty($name) || empty($gradeName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $gradeName = str_replace('Grade ', '', $gradeName);
            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $gradeName]);
            if ($grade) {
                if ($grade !== $learner->getGrade()) {
                    $results = $this->em->getRepository(Result::class)->findBy(['learner' => $learner]);
                    foreach ($results as $result) {
                        $this->em->remove($result);
                    }
                    $this->em->flush();
                }
            } else {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            $learner->setName($name);
            $learner->setGrade($grade);
            if (!empty($schoolName)) {
                $learner->setSchoolName($schoolName);
            }
            if (!empty($schoolAddress)) {
                $learner->setSchoolAddress($schoolAddress);
            }
            if (!empty($schoolLatitude)) {
                $learner->setSchoolLatitude($schoolLatitude);
            }
            if (!empty($schoolLongitude)) {
                $learner->setSchoolLongitude($schoolLongitude);
            }
            if (!empty($notificationHour)) {
                $learner->setNotificationHour($notificationHour);
            }
            if (!empty($terms)) {
                $learner->setTerms($this->cleanCommaString($terms));
            }
            if (!empty($curriculum)) {
                $this->logger->info("registration: " . $isRegistration);
                if ($isRegistration) {
                    $learner->setCurriculum('CAPS,IEB');
                } else {
                    $learner->setCurriculum($this->cleanCommaString($curriculum));
                }
            }
            //if curriculum is CAPS, set private school to false
            if ($curriculum === 'CAPS') {
                $learner->setPrivateSchool(false);
            } else {
                $learner->setPrivateSchool(true);
            }
            if (!empty($email)) {
                $learner->setEmail($email);
            }

            $this->em->persist($learner);
            $this->em->flush();


            return array(
                'status' => 'OK',
                'message' => 'Successfully updated learner'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error updating learner'
            );
        }
    }

    public function getGrades(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $grades = $this->em->getRepository(Grade::class)->findBy(['active' => true]);
            $formattedGrades = array_map(fn($grade) => [
                'id' => $grade->getId(),
                'number' => $grade->getNumber(),
                'active' => $grade->getActive()
            ], $grades);

            return [
                'status' => 'OK',
                'grades' => $formattedGrades
            ];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting grades'
            ];
        }
    }

    public function getLearnerSubjects(Request $request): array
    {
        try {
            $uid = $request->query->get('uid');
            if (empty($uid)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'UID is required'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            $grade = $learner->getGrade();
            if (!$grade) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            // Get learner's terms and curriculum as arrays
            $learnerTerms = $learner->getTerms() ? array_map('trim', explode(',', $learner->getTerms())) : [];
            $learnerCurriculum = $learner->getCurriculum() ? array_map('trim', explode(',', $learner->getCurriculum())) : [];

            // Build query to get subjects with question counts
            $qb = $this->em->createQueryBuilder();
            $qb->select('s.id, s.name, s.active, COUNT(DISTINCT q.id) as totalSubjectQuestions')
                ->from('App\Entity\Subject', 's')
                ->leftJoin(
                    'App\Entity\Question',
                    'q',
                    'WITH',
                    $qb->expr()->andX(
                        $qb->expr()->eq('q.subject', 's.id'),
                        $qb->expr()->eq('q.active', ':active'),
                        $qb->expr()->eq('q.status', ':status')
                    )
                )
                ->where('s.grade = :grade')
                ->andWhere('s.active = :subjectActive');


            $qb->groupBy('s.id')
                ->orderBy('s.name', 'ASC');

            // Set parameters
            $parameters = new ArrayCollection([
                new Parameter('grade', $grade),
                new Parameter('active', true),
                new Parameter('status', 'approved'),
                new Parameter('subjectActive', true)
            ]);


            $qb->setParameters($parameters);

            $subjects = $qb->getQuery()->getResult();

            // Get total results for each subject
            foreach ($subjects as &$subject) {
                $resultsQb = $this->em->createQueryBuilder();
                $resultsQb->select('COUNT(r.id) as totalResults')
                    ->from('App\Entity\Result', 'r')
                    ->join('r.question', 'q')
                    ->where('r.learner = :learner')
                    ->andWhere('q.subject = :subject')
                    ->andWhere('q.active = :active')
                    ->andWhere('q.status = :status');

                // Add term condition if learner has terms specified
                if (!empty($learnerTerms)) {
                    $resultsQb->andWhere('q.term IN (:terms)');
                }

                // Add curriculum condition if learner has curriculum specified
                if (!empty($learnerCurriculum)) {
                    $resultsQb->andWhere('q.curriculum IN (:curriculum)');
                }

                $resultParameters = new ArrayCollection([
                    new Parameter('learner', $learner),
                    new Parameter('subject', $subject['id']),
                    new Parameter('active', true),
                    new Parameter('status', 'approved')
                ]);

                if (!empty($learnerTerms)) {
                    $resultParameters->add(new Parameter('terms', $learnerTerms));
                }

                if (!empty($learnerCurriculum)) {
                    $resultParameters->add(new Parameter('curriculum', $learnerCurriculum));
                }

                $resultsQb->setParameters($resultParameters);

                $totalResults = $resultsQb->getQuery()->getSingleScalarResult();
                $subject['totalResults'] = $totalResults;
            }

            return array(
                'status' => 'OK',
                'subjects' => $subjects
            );

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting learner subjects'
            );
        }
    }


    function normalizeString($string)
    {
        // Replace different types of hyphens and minus signs with a standard hyphen
        $string = str_replace(['−', '–', '—', '―', '−'], '-', $string);
        // Remove any leading or trailing whitespace
        return trim($string);
    }

    /**
     * Normalize numeric answer by removing formatting
     * 
     * @param string $answer Answer to normalize
     * @return string Normalized answer
     */
    private function normalizeNumericAnswer(string $answer): string
    {
        // Remove spaces and convert commas to dots
        $normalized = str_replace([' ', ','], ['', '.'], trim($answer));

        // Remove currency symbols and spaces
        $hasCurrency = preg_match('/^[R$€£]/', $normalized);
        $normalized = preg_replace('/^[R$€£]/', '', $normalized);

        // Remove percentage sign if present
        $hasPercentage = str_contains($normalized, '%');
        $normalized = str_replace('%', '', $normalized);

        // If it's a numeric value, format it consistently
        if (is_numeric($normalized)) {
            // Convert to float and back to string to handle both integer and decimal formats
            $value = (string) floatval($normalized);
            // Add back the appropriate symbol
            if ($hasCurrency) {
                return 'R' . $value;
            }
            return $hasPercentage ? $value . '%' : $value;
        }

        return $normalized;
    }

    public function checkLearnerAnswer(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'];
            $learnerAnswers = trim($requestBody['answer']);
            $RequestType = $requestBody['requesting_type'];

            $learnerAnswers = str_replace(' ', '', $learnerAnswers);
            $uid = $requestBody['uid'];

            if (empty($questionId) || empty($uid)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }

            if (empty($learnerAnswers)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing - ' . $question->getType()
                );
            }

            if (!is_array($learnerAnswers)) {
                $learnerAnswers = [$learnerAnswers];
            }

            $correctAnswers = json_decode($question->getAnswer(), true);
            $learnerAnswer = $requestBody['answer'];

            // Normalize both correct and learner answers
            $normalizedLearnerAnswer = $this->normalizeNumericAnswer($learnerAnswer);
            $isCorrect = false;

            foreach ($correctAnswers as $correctAnswer) {


                $normalizedCorrectAnswer = $this->normalizeNumericAnswer($correctAnswer);

                //replace spaces from both answers
                $normalizedCorrectAnswer = str_replace(' ', '', $normalizedCorrectAnswer);
                $normalizedLearnerAnswer = str_replace(' ', '', $normalizedLearnerAnswer);

                // If either answer has a currency symbol or percentage, normalize both to numeric
                if (
                    preg_match('/^[R$€£]/', $normalizedCorrectAnswer) ||
                    preg_match('/^[R$€£]/', $normalizedLearnerAnswer) ||
                    str_contains($normalizedCorrectAnswer, '%') ||
                    str_contains($normalizedLearnerAnswer, '%')
                ) {

                    $correctValue = preg_replace('/^[R$€£$]/', '', $normalizedCorrectAnswer);
                    $correctValue = str_replace('%', '', $correctValue);

                    $learnerValue = preg_replace('/^[R$€£$]/', '', $normalizedLearnerAnswer);
                    $learnerValue = str_replace('%', '', $learnerValue);

                    if (floatval($correctValue) === floatval($learnerValue)) {
                        $isCorrect = true;
                        break;
                    }
                } else if ($normalizedCorrectAnswer === $normalizedLearnerAnswer) {
                    $isCorrect = true;
                    break;
                }

                $this->logger->info("correctAnswer: " . $normalizedCorrectAnswer);
                $this->logger->info("normalizedLearnerAnswer: " . $normalizedLearnerAnswer);
            }

            //update learner score, add one if correct, minus one if incorrect. should not go below 0
            if ($isCorrect) {
                // Get the last 3 results for this learner
                $lastResults = $this->em->getRepository(Result::class)
                    ->createQueryBuilder('r')
                    ->where('r.learner = :learner')
                    ->orderBy('r.created', 'DESC')
                    ->setMaxResults(3)
                    ->setParameter('learner', $learner)
                    ->getQuery()
                    ->getResult();

                // Check if last 2 results were also correct (making it 3 in a row with current)
                $this->logger->info("lastResults: " . count($lastResults));
                if (
                    count($lastResults) === 3 &&
                    $lastResults[0]->getOutcome() === 'correct' &&
                    $lastResults[1]->getOutcome() === 'correct' &&
                    $lastResults[2]->getOutcome() === 'correct'
                ) {
                    // Add bonus points for 3 in a row
                    $learner->setScore($learner->getScore() + 3); // 1 for current + 3 bonus
                } else {
                    // Normal point for correct answer
                    $learner->setScore($learner->getScore() + 1);
                }
            } else {
                $learner->setScore($learner->getScore() - 1);
            }

            if ($learner->getScore() < 0) {
                $learner->setScore(0);
            }

            $this->em->persist($learner);
            $this->em->flush();

            // Create result record
            if ($RequestType !== 'mock') {
                $result = new Result();
                $result->setLearner($learner);
                $result->setQuestion($question);
                $result->setOutcome($isCorrect ? 'correct' : 'incorrect');
                $result->setCreated(new \DateTime());

                $this->em->persist($result);
                $this->em->flush();
            }
            ;

            return array(
                'status' => 'OK',
                'result' => $isCorrect ? 'correct' : 'incorrect',
                'is_correct' => $isCorrect
            );

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error checking answer'
            );
        }
    }



    public function removeLearnerResultsBySubject(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $subjectName = $requestBody['subject_name'];

            if (empty($uid) || empty($subjectName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            $subject = $this->em->getRepository(Subject::class)->findOneBy(['name' => $subjectName, 'grade' => $learner->getGrade()]);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            $results = $this->em->getRepository(Result::class)->createQueryBuilder('r')
                ->join('r.question', 'q')
                ->where('r.learner = :learner')
                ->andWhere('q.subject = :subject')
                ->setParameter('learner', $learner)
                ->setParameter('subject', $subject)
                ->getQuery()
                ->getResult();

            foreach ($results as $result) {
                $this->em->remove($result);
            }
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully removed learner results for the subject'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error removing learner results'
            );
        }
    }


    public function getAllActiveSubjects($request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $gradeNumber = $request->query->get('grade');
            if (empty($gradeNumber)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade is required'
                );
            }

            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $gradeNumber]);
            if (!$grade) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            $subjects = $this->em->getRepository(Subject::class)->findBy(['active' => true, 'grade' => $grade], ['name' => 'ASC']);
            return array(
                'status' => 'OK',
                'subjects' => $subjects
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting active subjects'
            );
        }
    }

    public function uploadImage(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $file = $request->files->get('image');
            $questionId = $request->request->get('question_id');
            $imageType = $request->request->get('image_type');

            if (!$file) {
                return array(
                    'status' => 'NOK',
                    'message' => 'No image file provided'
                );
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }

            $uploadDir = $this->projectDir . '/public/assets/images/learnMzansi';
            $newFilename = uniqid() . '.' . $file->guessExtension();

            $file->move($uploadDir, $newFilename);
            $this->logger->debug("File uploaded: $newFilename");
            if ($imageType == 'question_context') {
                $question->setImagePath($newFilename);
            } elseif ($imageType == 'question') {
                $question->setQuestionImagePath($newFilename);
            } elseif ($imageType == 'answer') {
                $question->setAnswerImage($newFilename);
            } else {
                return array(
                    'status' => 'NOK',
                    'message' => 'Invalid image type'
                );
            }

            $this->em->persist($question);
            $this->em->flush();


            return array(
                'status' => 'OK',
                'message' => 'Image successfully uploaded',
                'fileName' => $newFilename
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error uploading image'
            );
        }
    }

    public function setImagePathForQuestion(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'];
            $imageName = $requestBody['image_name'];

            if (empty($questionId) || empty($imageName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }

            $question->setImagePath($imageName);
            $this->em->persist($question);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully set image path for question'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error setting image path for question'
            );
        }
    }

    public function setImageForQuestionAnswer(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'];
            $imageName = $requestBody['image_name'];

            if (empty($questionId) || empty($imageName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }

            $question->setAnswerImage($imageName);
            $this->em->persist($question);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully set image path for question'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error setting image path for question'
            );
        }
    }

    public function getQuestionsByGradeAndSubject(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $gradeNumber = $request->query->get('grade');
            $subjectName = $request->query->get('subject');
            $status = $request->query->get('status');

            if (empty($gradeNumber) || empty($subjectName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade and Subject and Status are required'
                );
            }

            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $gradeNumber]);
            if (!$grade) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            $subject = $this->em->getRepository(Subject::class)->findOneBy(['name' => $subjectName, 'grade' => $grade]);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            if (empty($status)) {
                $questions = $this->em->getRepository(Question::class)->findBy(['subject' => $subject, 'active' => 1], ['created' => 'DESC']);
            } else {
                $questions = $this->em->getRepository(Question::class)->findBy(['subject' => $subject, 'status' => $status, 'active' => 1], ['created' => 'DESC']);
            }

            return array(
                'status' => 'OK',
                'questions' => $questions
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting questions'
            );
        }
    }


    public function setQuestionInactive(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'];

            if (empty($questionId)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question ID is required'
                );
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }
            $this->em->remove($question);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully set question to inactive'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error setting question to inactive'
            );
        }
    }

    public function setQuestionStatus(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            // $adminCheck = $this->validateAdminAccess($request);
            // if ($adminCheck['status'] === 'NOK' && $requestBody['status'] !== 'rejected') {
            //     return $adminCheck;
            // }

            $questionId = $requestBody['question_id'];
            $status = $requestBody['status'];
            $reviewerEmail = $requestBody['email'];
            $comment = $requestBody['comment'];
            $uid = $requestBody['uid'];

            if (empty($questionId) || empty($status) || empty($reviewerEmail)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'fields are required'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Reviewer not found'
                );
            }

            //only learner with role admin can approve a question
            if ($learner->getRole() !== 'admin' && $status === 'approved') {
                return array(
                    'status' => 'NOK',
                    'message' => 'Only admin can approve a question'
                );
            }

            //if status is reject then comment is required
            if ($status == 'rejected' && empty($comment)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Comment is required'
                );
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }

            //reviewer can not be the same as capturer
            if ($question->getCapturer() === $reviewerEmail) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Reviewer can not be the same as capturer'
                );
            }

            $question->setStatus($status);
            $question->setReviewer($learner);
            if (!empty($comment)) {
                $question->setComment($comment);
            }
            if ($status == 'approved') {
                $question->setComment(comment: "approved");
            }
            $this->em->persist($question);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully set question to ' . $status
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error setting question to ' . $status
            );
        }
    }


    /**
     * Helper method to check if a user has admin role
     */
    private function isAdmin(string $uid): bool
    {
        $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        return $learner && $learner->getRole() === 'admin';
    }

    /**
     * Helper method to validate admin access using uid from request body
     */
    private function validateAdminAccess(Request $request): array
    {
        $requestBody = json_decode($request->getContent(), true);
        $uid = $requestBody['uid'] ?? null;

        if (empty($uid)) {
            return array(
                'status' => 'NOK',
                'message' => 'User ID is required'
            );
        }

        if (!$this->isAdmin($uid)) {
            return array(
                'status' => 'NOK',
                'message' => 'Unauthorized: Admin access required'
            );
        }
        return array('status' => 'OK');
    }

    public function createSubject(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $name = $requestBody['name'];
            $gradeNumber = $requestBody['grade'];

            if (empty($name) || empty($gradeNumber)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject name and grade are required'
                );
            }

            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $gradeNumber]);
            if (!$grade) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            // Check if subject already exists for this grade
            $existingSubject = $this->em->getRepository(Subject::class)->findOneBy([
                'name' => $name,
                'grade' => $grade
            ]);

            if ($existingSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject already exists for this grade'
                );
            }

            $subject = new Subject();
            $subject->setName($name);
            $subject->setGrade($grade);
            $subject->setActive(true);

            $this->em->persist($subject);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully created subject',
                'subject_id' => $subject->getId()
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error creating subject'
            );
        }
    }

    public function updateSubjectActive(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $subjectId = $requestBody['subject_id'];
            $active = $requestBody['active'];

            if (empty($subjectId)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject ID is required'
                );
            }

            $subject = $this->em->getRepository(Subject::class)->find($subjectId);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            $subject->setActive($active);
            $this->em->persist($subject);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully updated subject active status'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error updating subject active status'
            );
        }
    }

    public function getSubjectsByGrade(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $gradeNumber = $request->query->get('grade');

            if (empty($gradeNumber)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade number is required'
                );
            }

            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $gradeNumber]);
            if (!$grade) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            $subjects = $this->em->getRepository(Subject::class)->findBy(['grade' => $grade], ['name' => 'ASC']);

            return array(
                'status' => 'OK',
                'subjects' => $subjects
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting subjects'
            );
        }
    }

    public function getDistinctSubjectNames(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('DISTINCT s.name')
                ->from('App\Entity\Subject', 's')
                ->where('s.active = :active')
                ->setParameter('active', true)
                ->orderBy('s.name', 'ASC');

            $query = $queryBuilder->getQuery();
            $results = $query->getResult();

            // Extract just the names from the result array
            $subjectNames = array_map(function ($item) {
                return $item['name'];
            }, $results);

            return array(
                'status' => 'OK',
                'subjects' => $subjectNames
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting subject names'
            );
        }
    }

    public function getQuestionsCaptured(Request $request): array
    {
        try {
            $fromDate = $request->query->get('from_date');
            if (!$fromDate) {
                $fromDate = (new \DateTime())->modify('-4 weeks')->format('Y-m-d');
            }

            $conn = $this->em->getConnection();
            $sql = '
                SELECT 
                    l.name as capturer_name,
                    q.status,
                    COUNT(q.id) as question_count
                FROM question q
                JOIN learner l ON q.capturer = l.id
                WHERE q.created >= :fromDate
                GROUP BY l.id, q.status
                ORDER BY l.name ASC, q.status ASC
            ';

            $stmt = $conn->prepare($sql);
            $stmt->bindValue('fromDate', $fromDate);
            $results = $stmt->executeQuery()->fetchAllAssociative();

            // Format the results by capturer
            $formattedResults = [];
            foreach ($results as $result) {
                $displayName = sprintf(
                    '%s (%s)',
                    $result['capturer_name'],
                    ucfirst($result['status'])
                );

                $formattedResults[] = [
                    'name' => $displayName,
                    'count' => (int) $result['question_count']
                ];
            }

            return [
                'status' => 'OK',
                'data' => $formattedResults
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting questions captured'
            ];
        }
    }

    public function getTopIncorrectQuestions(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Get start and end dates for the current week
            $startDate = new \DateTime('monday this week');
            $endDate = new \DateTime('sunday this week');
            $startDate->setTime(0, 0, 0);
            $endDate->setTime(23, 59, 59);

            // Create query builder for total attempts and incorrect counts
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('q as question, 
                                 COUNT(r.id) as total_attempts,
                                 SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) as incorrect_count,
                                 (SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) * 100.0 / COUNT(r.id)) as failure_rate')
                ->from('App\Entity\Question', 'q')
                ->join('App\Entity\Result', 'r', 'WITH', 'r.question = q')
                ->where('r.created BETWEEN :startDate AND :endDate')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status')
                ->groupBy('q.id')
                ->having('COUNT(r.id) >= :min_attempts') // Only include questions with minimum attempts
                ->orderBy('failure_rate', 'DESC') // Order by calculated failure rate
                ->setMaxResults(5)
                ->setParameter('incorrect', 'incorrect')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->setParameter('active', true)
                ->setParameter('status', 'approved')
                ->setParameter('min_attempts', 5);

            $results = $queryBuilder->getQuery()->getResult();

            // Format the results
            $formattedResults = [];
            foreach ($results as $result) {
                $question = $result['question'];
                $totalAttempts = $result['total_attempts'];
                $incorrectCount = $result['incorrect_count'];
                $failureRate = round($result['failure_rate'], 2);

                $formattedResults[] = [
                    'question_id' => $question->getId(),
                    'question_text' => $question->getQuestion(),
                    'subject' => $question->getSubject()->getName(),
                    'grade' => $question->getSubject()->getGrade()->getNumber(),
                    'total_attempts' => $totalAttempts,
                    'incorrect_count' => $incorrectCount,
                    'failure_rate' => $failureRate,
                    'week' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')
                ];
            }

            return array(
                'status' => 'OK',
                'data' => $formattedResults
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting top incorrect questions'
            );
        }
    }

    public function getLearnersCreatedPerMonth(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Create query builder to get the first and last learner creation dates
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('MIN(SUBSTRING(l.created, 1, 7)) as first_month, MAX(SUBSTRING(l.created, 1, 7)) as last_month')
                ->from('App\Entity\Learner', 'l');

            $dateRange = $queryBuilder->getQuery()->getSingleResult();

            if (!$dateRange['first_month']) {
                return array(
                    'status' => 'OK',
                    'data' => [],
                    'total_learners' => 0
                );
            }

            // Create query builder for monthly counts
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('SUBSTRING(l.created, 1, 7) as month, COUNT(l.id) as learner_count')
                ->from('App\Entity\Learner', 'l')
                ->groupBy('month')
                ->orderBy('month', 'ASC');

            $results = $queryBuilder->getQuery()->getResult();

            // Format the results
            $formattedResults = [];
            $totalLearners = 0;
            foreach ($results as $result) {
                $date = \DateTime::createFromFormat('Y-m', $result['month']);
                if ($date) {
                    $formattedResults[] = [
                        'month' => $date->format('F Y'),
                        'month_key' => $result['month'], // YYYY-MM format for sorting
                        'count' => $result['learner_count']
                    ];
                    $totalLearners += $result['learner_count'];
                }
            }

            // Format the date range
            $startDate = \DateTime::createFromFormat('Y-m', $dateRange['first_month']);
            $endDate = \DateTime::createFromFormat('Y-m', $dateRange['last_month']);

            return array(
                'status' => 'OK',
                'data' => $formattedResults,
                'total_learners' => $totalLearners,
                'date_range' => [
                    'start' => $startDate ? $startDate->format('F Y') : '',
                    'end' => $endDate ? $endDate->format('F Y') : ''
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting learners created per month'
            );
        }
    }

    /**
     * Get count of questions in new status
     * 
     * @return array Status and count of questions in new status
     */
    public function getNewQuestionsCount(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('COUNT(q.id)')
                ->from('App\Entity\Question', 'q')
                ->where('q.status = :status')
                ->setParameter('status', 'new');

            $count = $queryBuilder->getQuery()->getSingleScalarResult();

            return array(
                'status' => 'OK',
                'count' => $count
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting new questions count: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get the next new question for review based on subject
     * 
     * @param int $questionId Current question ID to get its subject
     * @return array Question data or status message
     */
    public function getNextNewQuestion(int $questionId): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Get current question to determine subject
            $currentQuestion = $this->em->getRepository(Question::class)->find($questionId);
            if (!$currentQuestion) {
                return [
                    'status' => 'NOK',
                    'message' => 'Current question not found'
                ];
            }

            $subject = $currentQuestion->getSubject();

            // Get next new question from same subject
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('q')
                ->from('App\Entity\Question', 'q')
                ->where('q.subject = :subject')
                ->andWhere('q.status = :status')
                ->andWhere('q.id != :currentId')
                ->andWhere('q.active = :active')
                ->andWhere('q.comment = :comment')
                ->setParameter('subject', $subject)
                ->setParameter('status', 'approved')
                ->setParameter('currentId', $questionId)
                ->setParameter('active', true)
                ->setParameter('comment', "new")
                ->setMaxResults(1);

            $question = $queryBuilder->getQuery()->getOneOrNullResult();

            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'No more new questions available for this subject'
                ];
            }

            return [
                'status' => 'OK',
                'question' => $question
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting next new question: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all rejected questions for a specific capturer
     * 
     * @param string $capturer The capturer's email/id
     * @return array List of rejected questions or status message
     */
    public function getRejectedQuestionsByCapturer(string $capturer): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            if (empty($capturer)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Capturer is required'
                ];
            }

            // Get all rejected questions for the capturer
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('q')
                ->from('App\Entity\Question', 'q')
                ->where('q.capturer = :capturer')
                ->andWhere('q.status = :status')
                ->andWhere('q.active = :active')
                ->orderBy('q.created', 'DESC')
                ->setParameter('capturer', $capturer)
                ->setParameter('status', 'rejected')
                ->setParameter('active', true);

            $questions = $queryBuilder->getQuery()->getResult();

            if (empty($questions)) {
                return [
                    'status' => 'OK',
                    'message' => 'No rejected questions found',
                    'questions' => []
                ];
            }

            return [
                'status' => 'OK',
                'questions' => $questions,
                'count' => count($questions)
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting rejected questions: ' . $e->getMessage()
            ];
        }
    }

    public function subscribe(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $phoneNumber = $request->query->get('phone');

            if (empty($phoneNumber)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Phone number is required'
                );
            }

            // Basic phone number validation
            if (!preg_match('/^[0-9]{10}$/', $phoneNumber)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Invalid phone number format. Please use 10 digits'
                );
            }

            // Check if phone number already exists
            $existingSubscription = $this->em->getRepository(Subscription::class)
                ->findOneBy(['phoneNumber' => $phoneNumber]);

            if ($existingSubscription) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Phone number already subscribed'
                );
            }

            // Create new subscription
            $subscription = new Subscription();
            $subscription->setPhoneNumber($phoneNumber);
            $subscription->setCreated(new \DateTime());

            $this->em->persist($subscription);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully subscribed'
            );

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error creating subscription'
            );
        }
    }

    //service to update the posted status of a question
    public function updateQuestionPostedStatus(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $questionId = $request->query->get('questionId');
        $posted = $request->query->get('posted');

        try {
            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Question not found'
                );
            }

            $question->setPosted($posted);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Question posted status updated'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error updating question posted status'
            );
        }
    }

    /**
     * Auto reject questions where answer options are significantly shorter than correct answer
     * 
     * @return array Status and count of rejected questions
     */
    public function autoRejectQuestions(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Get all approved questions that haven't been auto-checked
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('q')
                ->from('App\Entity\Question', 'q')
                ->where('q.status = :status')
                ->andWhere('q.active = :active')
                ->andWhere('q.type IN (:types)')
                ->setParameter('status', 'approved')
                ->setParameter('active', true)
                ->setParameter('types', ['multiple_choice', 'multi_select']);

            $questions = $queryBuilder->getQuery()->getResult();

            $rejectedCount = 0;

            foreach ($questions as $question) {
                $options = $question->getOptions();
                $answer = json_decode($question->getAnswer(), true);

                // Skip if no options or answer
                if (!$options || !$answer || !is_array($answer)) {
                    continue;
                }

                // Get correct answer length
                $correctAnswerLength = strlen($answer[0]);


                // If there are multiple correct answers, get average length
                $correctAnswerLength = $correctAnswerLength / count($answer);

                // Calculate average length of incorrect options
                $incorrectLengths = [];
                $numberOfIncorrectOptions = 0;
                foreach ($options as $key => $option) {
                    if (strpos($option, $answer[0]) === false) {
                        $incorrectLengths[] = strlen($option);
                        $numberOfIncorrectOptions++;
                    }
                }

                if ($numberOfIncorrectOptions == 4) {
                    $this->logger->info("No correct option found for question: " . $question->getId());
                    continue;
                }

                if (empty($incorrectLengths)) {
                    $this->logger->info("No incorrect options found for question: " . $question->getId());
                    continue;
                }

                $avgIncorrectLength = array_sum($incorrectLengths) / count($incorrectLengths);

                // Reject if average incorrect length is more than 20 chars shorter
                if (($correctAnswerLength - $avgIncorrectLength) > 20) {
                    $question->setStatus('rejected');
                    $question->setComment('Auto-rejected: answer length is ' . $correctAnswerLength . ' and average incorrect length is ' . $avgIncorrectLength);
                    $this->em->persist($question);
                    $rejectedCount++;
                }
            }

            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => "Auto-rejected $rejectedCount questions",
                'rejected_count' => $rejectedCount
            );

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error auto-rejecting questions: ' . $e->getMessage()
            );
        }
    }

    /**
     * Process question images and update context with extracted text
     * 
     * @return array Status and count of processed questions
     */
    public function processQuestionImages($count): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Get all questions with images that haven't been processed
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('q')
                ->from('App\Entity\Question', 'q')
                ->where('q.imagePath IS NOT NULL')
                ->andWhere('q.imagePath != :empty')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status')
                ->andWhere('q.comment = :comment')
                ->setParameter('empty', '')
                ->setParameter('active', true)
                ->setParameter('status', 'approved')
                ->setParameter('comment', 'new');

            $questions = $queryBuilder->getQuery()->getResult();

            $rejectedCount = 0;
            $processedCount = 0;

            $this->logger->info("Questions: " . count($questions));
            foreach ($questions as $question) {
                $imagePath = $question->getImagePath();
                if (!$imagePath) {
                    $this->logger->info("No image path found for question: " . $question->getImagePath());
                    continue;
                }

                // Check file size
                $this->logger->info("Image path: " . $imagePath);
                $fullPath = $this->projectDir . '/public/assets/images/learnMzansi/' . $imagePath;
                if (!file_exists($fullPath)) { // 200KB
                    $this->logger->info("Image file does not exist . " . $fullPath);
                    continue;
                }

                $this->logger->info("File size: " . filesize($fullPath));
                if (filesize($fullPath) > 200 * 1024) { // 200KB
                    $this->logger->info("Image file is too large. " . $fullPath);
                    continue;
                }

                $imageUrl = "https://api.examquiz.co.za/public/learn/learner/get-image?image=" . $imagePath;

                $this->logger->info("Image URL: " . $imageUrl);
                $data = [
                    "model" => "gpt-4o-mini",
                    "messages" => [
                        [
                            "role" => "system",
                            "content" => "You are an AI that checks if an image contains only text. Return true if the image contains only text, return false if the image contains any objects, diagrams, or mixed content."
                        ],
                        [
                            "role" => "user",
                            "content" => [
                                [
                                    "type" => "image_url",
                                    "image_url" => ["url" => $imageUrl]
                                ]
                            ]
                        ]
                    ]
                ];


                $processedCount++;
                $ch = curl_init('https://api.openai.com/v1/chat/completions');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openAiKey
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if (isset($result['choices'][0]['message']['content'])) {
                        $isTextOnly = strtolower(trim($result['choices'][0]['message']['content'])) === 'true';

                        if ($isTextOnly) {
                            $question->setStatus('rejected');
                            $question->setComment('Rejected by AI: Image is text only');
                            $this->em->persist($question);
                            $rejectedCount++;
                        } else {
                            $question->setComment('Image checked by AI: Image is not text only');
                            $this->em->persist($question);
                        }
                    }
                }

                if ($processedCount >= $count) {
                    break;
                }
            }

            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => "Rejected $rejectedCount questions with text-only images",
                'rejected_count' => $rejectedCount
            );

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error processing question images: ' . $e->getMessage()
            );
        }
    }

    public function getAIExplanation(int $questionId): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            //if question ai explanation is not null, return
            if ($question->getAiExplanation()) {
                return [
                    'status' => 'OK',
                    'explanation' => $question->getAiExplanation()
                ];
            }

            $messages = [
                [
                    "role" => "system",
                    "content" => "You are an AI tutor that explains answers to questions based on their context. Follow these rules:\n1. Read the provided context and analyze any accompanying images.\n2. Understand the question and the correct answer.\n3. Provide an explanation **only**—do not include the correct answer itself in the response.\n4. Format the explanation as **bullet points**.\n5. Avoid any introduction like 'The correct answer is...' or 'This is because...'.\n6. If needed, reference the context and images in your explanation."
                ],
                [
                    "role" => "user",
                    "content" => []
                ]
            ];

            // Add question context
            $messages[1]['content'][] = [
                "type" => "text",
                "text" => "Context: " . ($question->getContext() ?? "Choose an Answer that matches the description.")
            ];

            // Add question text
            $messages[1]['content'][] = [
                "type" => "text",
                "text" => "Question: " . $question->getQuestion()
            ];

            // Add correct answer
            $messages[1]['content'][] = [
                "type" => "text",
                "text" => "Correct Answer: " . $question->getAnswer()
            ];

            // Add image if exists
            if ($question->getImagePath()) {
                $imageUrl = "https://api.examquiz.co.za/public/learn/learner/get-image?image=" . $question->getImagePath();
                $messages[1]['content'][] = [
                    "type" => "image_url",
                    "image_url" => ["url" => $imageUrl]
                ];
            }

            //add image for questionImagePath
            if ($question->getQuestionImagePath()) {
                $imageUrl = "https://api.examquiz.co.za/public/learn/learner/get-image?image=" . $question->getQuestionImagePath();
                $messages[1]['content'][] = [
                    "type" => "image_url",
                    "image_url" => ["url" => $imageUrl]
                ];
            }

            $data = [
                "model" => "gpt-4o-mini",
                "messages" => $messages
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openAiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->error('OpenAI API error: ' . $response);
                return [
                    'status' => 'NOK',
                    'message' => 'Error getting AI explanation'
                ];
            }

            $result = json_decode($response, true);
            if (!isset($result['choices'][0]['message']['content'])) {
                return [
                    'status' => 'NOK',
                    'message' => 'Invalid response from OpenAI'
                ];
            }

            $explanation = $result['choices'][0]['message']['content'];

            // Update question with AI explanation
            $question->setAiExplanation($explanation);
            $this->em->flush();

            return [
                'status' => 'OK',
                'explanation' => $explanation
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error generating explanation: ' . $e->getMessage()
            ];
        }
    }

    public function createLearner(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $name = $requestBody['name'];
            $grade = $requestBody['grade'];
            $terms = $requestBody['terms'];
            $curriculum = $requestBody['curriculum'];
            $schoolName = $requestBody['school_name'];
            $schoolAddress = $requestBody['school_address'];
            $schoolLatitude = $requestBody['school_latitude'];
            $schoolLongitude = $requestBody['school_longitude'];
            $email = $requestBody['email'];

            if (empty($uid) || empty($terms) || empty($curriculum) || empty($schoolName) || empty($schoolAddress) || empty($schoolLatitude) || empty($schoolLongitude)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                $learner = new Learner();
                $learner->setUid($uid);
                if ($name) {
                    $learner->setName($name);
                }
                $learner->setScore(0);
                $learner->setCreated(new \DateTime());
                $learner->setNotificationHour(18);
                $learner->setTerms(json_encode($requestBody['terms']));
                $learner->setCurriculum(json_encode($requestBody['curriculum']));
                $learner->setSchoolName($requestBody['school_name']);
                $learner->setSchoolAddress($requestBody['school_address']);
                $learner->setSchoolLatitude($requestBody['school_latitude']);
                $learner->setSchoolLongitude($requestBody['school_longitude']);
                if (!empty($email)) {
                    $learner->setEmail($email);
                }

                $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $grade]);
                $learner->setGrade($grade);

                $learner->setLastSeen(new \DateTime());
                $this->em->persist($learner);
                $this->em->flush();

                return array(
                    'status' => 'OK',
                    'message' => 'Successfully created learner'
                );
            } else {
                $learner->setLastSeen(new \DateTime());
                $this->em->persist($learner);
                $this->em->flush();
                if ($learner->getGrade()) {
                    return array(
                        'status' => 'NOK',

                        'message' => "Learner already exists $uid",
                        'grade' => $learner->getGrade()->getNumber()
                    );
                } else {
                    return array(
                        'status' => 'NOK',
                        'message' => "Learner already exists $uid",
                        'grade' => "Not assigned"
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error creating learner'
            );
        }
    }

    public function getSchoolFact(Request $request): array
    {
        try {
            $schoolName = $request->query->get('school_name');

            if (!$schoolName) {
                return [
                    'status' => 'NOK',
                    'message' => 'School name is required'
                ];
            }

            $prompt = "Search for and provide an interesting fact about {$schoolName}, including historical milestones, notable alumni, unique traditions, or academic achievements. keep the response small, less than 20 words";

            $curl = curl_init();
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openAiKey
            ];

            $postData = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 50
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                $this->logger->error('cURL Error: ' . $err);
                return [
                    'status' => 'NOK',
                    'message' => 'Error getting school fact'
                ];
            }

            $responseData = json_decode($response, true);
            if (!isset($responseData['choices'][0]['message']['content'])) {
                $this->logger->error('Invalid response from OpenAI: ' . $response);
                return [
                    'status' => 'NOK',
                    'message' => 'Invalid response from AI'
                ];
            }

            $fact = $responseData['choices'][0]['message']['content'];

            return [
                'status' => 'OK',
                'fact' => trim($fact)
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting school fact'
            ];
        }
    }

    public function updateLearnerRating(Request $request): array
    {
        try {
            $uid = $request->request->get('uid');
            $rating = $request->request->get('rating');

            if (!$uid || !$rating) {
                return [
                    'status' => 'NOK',
                    'message' => 'Missing required parameters'
                ];
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $rating = floatval($rating);
            if ($rating < 0 || $rating > 5) {
                return [
                    'status' => 'NOK',
                    'message' => 'Rating must be between 0 and 5'
                ];
            }

            $learner->setRating($rating);
            $learner->setRatingCancelled(null); // Clear any previous cancellation

            $this->em->persist($learner);
            $this->em->flush();

            return [
                'status' => 'OK',
                'message' => 'Rating updated successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error updating rating'
            ];
        }
    }

    public function cancelLearnerRating(Request $request): array
    {
        try {
            $uid = $request->request->get('uid');

            if (!$uid) {
                return [
                    'status' => 'NOK',
                    'message' => 'Missing required parameters'
                ];
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $learner->setRatingCancelled(new \DateTime());
            $learner->setRating(0); // Reset rating to 0

            $this->em->persist($learner);
            $this->em->flush();

            return [
                'status' => 'OK',
                'message' => 'Rating cancelled successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error cancelling rating'
            ];
        }
    }

    public function deleteLearner(Request $request): array
    {
        try {
            $uid = $request->request->get('uid');

            if (!$uid) {
                return [
                    'status' => 'NOK',
                    'message' => 'Missing required parameter: uid'
                ];
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Begin transaction
            $this->em->beginTransaction();
            try {
                // Delete associated results first
                $results = $this->em->getRepository(Result::class)->findBy(['learner' => $learner->getId()]);
                foreach ($results as $result) {
                    $this->em->remove($result);
                }

                //delete learner results
                $results = $this->em->getRepository(Result::class)->findBy(['learner' => $learner->getId()]);
                foreach ($results as $result) {
                    $this->em->remove($result);
                }

                //delete learner
                $this->em->remove($learner);
                $this->em->flush();
                $this->em->commit();

                return [
                    'status' => 'OK',
                    'message' => 'Learner and associated data deleted successfully'
                ];

            } catch (\Exception $e) {
                $this->em->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error('Error deleting learner: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error deleting learner'
            ];
        }
    }
}
