<?php

namespace App\Service;

use App\Entity\Grade;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Learner;
use App\Entity\Learnersubjects;
use App\Entity\Question;
use App\Entity\Result;
use App\Entity\Subject;
use App\Entity\Issue;
use phpDocumentor\Reflection\Types\Boolean;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;

class LearnMzansiApi extends AbstractController
{
    private $em;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->em = $entityManager;
        $this->logger = $logger;
    }

    public function createLearner(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $name = $requestBody['name'];

            if (empty($uid)) {
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
                $learner->setOverideTerm(true);
                $learner->setCreated(new \DateTime());
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



            $questionId = $data['question_id'] ?? null;

            // Validate required fields
            if (empty($data['type']) || empty($data['subject']) || empty($data['year']) || empty($data['term']) || empty($data['answer'])) {
                return array(
                    'status' => 'NOK',
                    'message' => "Missing required fields."
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

            // Fetch the associated Subject entity
            $subject = $this->em->getRepository(Subject::class)->findOneBy(['name' => $data['subject']]);
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


            $this->logger->info("debug 1");

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
            $question->setCapturer($data['capturer'] ?? null);
            $question->setReviewer($data['capturer'] ?? null);
            $question->setCreated(new \DateTime());
            $question->setActive(true);
            $question->setStatus('approved');
            $question->setComment("new");

            $this->logger->info("debug 2");
            // Persist and flush the new entity
            $this->em->persist($question);
            $this->em->flush();

            $this->logger->info("debug 3");

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

    public function getRandomQuestionBySubjectId(int $subjectId, string $uid, int $questionId, string $showAllQuestions)
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        try {

            $termCondition = '';
            $statusCondition = '';

            if ($questionId !== 0) {
                $query = $this->em->createQuery(
                    'SELECT q
                    FROM App\Entity\Question q
                    WHERE q.id = :id'
                )->setParameter('id', $questionId);

                $question = $query->getOneOrNullResult();
                if ($question) {
                    return $question;
                } else {
                    return array(
                        'status' => 'NOK',
                        'message' => 'Question not found'
                    );
                }
            }


            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }


            $learnerSubject = $this->em->getRepository(Learnersubjects::class)->findOneBy(['learner' => $learner, 'subject' => $subjectId]);

            if ($showAllQuestions == 'no') {
                //pausing functionality, will return all questions for now
                $this->logger->info("filter by term");
                $termCondition = 'AND q.term = 2 ';
            }

            if ($learner->getName() == 'admin') {
                $statusCondition = '';
            } else {
                $statusCondition = ' AND q.status = \'approved\' ';
            }

            if (!$learnerSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner subject not found'
                );
            }

            $query = $this->em->createQuery(
                'SELECT q
            FROM App\Entity\Question q
            JOIN q.subject s
            LEFT JOIN App\Entity\Result r WITH r.question = q AND r.learner = :learner AND r.outcome = \'correct\'
            WHERE s.id = :subjectId 
            AND r.id IS NULL
            AND q.active = 1 ' . $termCondition . $statusCondition
            )->setParameter('subjectId', $subjectId)->setParameter('learner', $learner);

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

            return $randomQuestion;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function getRandomQuestionBySubjectName(string $subjectName, string $paperName, string $uid, int $questionId, string $showAllQuestions)
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        try {

            $termCondition = '';
            $statusCondition = '';

            if ($questionId !== 0) {
                $query = $this->em->createQuery(
                    'SELECT q
                    FROM App\Entity\Question q
                    WHERE q.id = :id'
                )->setParameter('id', $questionId);

                $question = $query->getOneOrNullResult();
                if ($question) {
                    return $question;
                } else {
                    return array(
                        'status' => 'NOK',
                        'message' => 'Question not found'
                    );
                }
            }


            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            //get subject by name
            $subject = $this->em->getRepository(Subject::class)->findOneBy(['name' => $subjectName . ' ' . $paperName]);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            $subjectId = $subject->getId();

            $learnerSubject = $this->em->getRepository(Learnersubjects::class)->findOneBy(['learner' => $learner, 'subject' => $subjectId]);

            if ($showAllQuestions == 'no') {
                //pausing functionality, will return all questions for now
                $this->logger->info("filter by term");
                $termCondition = 'AND q.term = 2 ';
            }

            if ($learner->getName() == 'admin') {
                $statusCondition = '';
            } else {
                $statusCondition = ' AND q.status = \'approved\' ';
            }

            if (!$learnerSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner subject not found'
                );
            }

            $query = $this->em->createQuery(
                'SELECT q
            FROM App\Entity\Question q
            JOIN q.subject s
            LEFT JOIN App\Entity\Result r WITH r.question = q AND r.learner = :learner AND r.outcome = \'correct\'
            WHERE s.id = :subjectId 
            AND r.id IS NULL
            AND q.active = 1 ' . $termCondition . $statusCondition
            )->setParameter('subjectId', $subjectId)->setParameter('learner', $learner);

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

            return $randomQuestion;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function updateLearner(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $name = $requestBody['name'] ?? null;
            $gradeName = $requestBody['grade'] ?? null;

            $this->logger->info("UID: $uid, Name: $name, Grade: $gradeName");

            if (empty($uid) || empty($name) || empty($gradeName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                $learner = new Learner();
                $learner->setUid($uid);
                $learner->setCreated(new \DateTime());
                $this->em->persist($learner);
                $this->em->flush();
            }

            $gradeName = str_replace('Grade ', '', $gradeName);
            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $gradeName]);
            if ($grade) {
                if ($grade !== $learner->getGrade()) {
                    //remove all learner subject and results
                    $learnerSubjects = $this->em->getRepository(Learnersubjects::class)->findBy(['learner' => $learner]);
                    foreach ($learnerSubjects as $learnerSubject) {
                        $this->em->remove($learnerSubject);
                    }
                    $this->em->flush();

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
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $uid = $request->query->get('uid');

            //test
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

            $learnerSubjects = $this->em->getRepository(Learnersubjects::class)->findBy(['learner' => $learner], ['lastUpdated' => 'DESC']);

            if (empty($learnerSubjects)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'No subjects found for learner'
                );
            }
            $answeredQuestions = 0;

            $returnArray = array();
            foreach ($learnerSubjects as $learnerSubject) {
                $query = $this->em->createQueryBuilder()
                    ->select('r, q')
                    ->from('App\Entity\Result', 'r')
                    ->join('r.question', 'q')
                    ->where('r.learner = :learner')
                    ->andWhere('q.subject = :subject')
                    ->setParameter('learner', $learner)
                    ->setParameter('subject', $learnerSubject->getSubject())
                    ->getQuery();


                $results = $query->getResult();
                $answeredQuestions = 0;

                //number of correct answers
                $correctAnswers = 0;
                foreach ($results as $result) {
                    $answeredQuestions++;
                    if ($result->getOutcome() === 'correct') {
                        $correctAnswers++;
                    }
                }

                $totalSubjectQuestion = $this->em->getRepository(Question::class)->createQueryBuilder('q')
                    ->select('count(q.id)')
                    ->where('q.subject = :subject')
                    ->andWhere('q.status = \'approved\'')
                    ->andWhere('q.active = 1')
                    ->setParameter('subject', $learnerSubject->getSubject())
                    ->getQuery()
                    ->getSingleScalarResult();

                $returnArray[] = array(
                    'subject' => $learnerSubject,
                    'total_questions' => $totalSubjectQuestion,
                    'answered_questions' => $answeredQuestions,
                    'correct_answers' => $correctAnswers
                );
            }

            return $returnArray;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting learner subjects'
            );
        }
    }

    public function assignSubjectToLearner(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $subjectId = $requestBody['subject_id'];

            if (empty($uid) || empty($subjectId)) {
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

            $subject = $this->em->getRepository(Subject::class)->find($subjectId);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            $existingLearnerSubject = $this->em->getRepository(Learnersubjects::class)->findOneBy([
                'learner' => $learner,
                'subject' => $subject
            ]);

            if ($existingLearnerSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject already assigned to learner'
                );
            }

            $learnerSubject = new Learnersubjects();
            $learnerSubject->setLearner($learner);
            $learnerSubject->setSubject($subject);
            $learnerSubject->setLastUpdated(new \DateTime());
            $learnerSubject->setPercentage(0);
            $this->em->persist($learnerSubject);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully assigned subject to learner'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error assigning subject to learner'
            );
        }
    }

    public function getSubjectsNotEnrolledByLearner(Request $request): array
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

            $enrolledSubjects = $this->em->getRepository(Learnersubjects::class)->findBy(['learner' => $learner]);
            $enrolledSubjectIds = array_map(function ($learnerSubject) {
                return $learnerSubject->getSubject()->getId();
            }, $enrolledSubjects);

            if (empty($enrolledSubjectIds)) {
                $queryBuilder = $this->em->createQueryBuilder();
                $queryBuilder->select('s')
                    ->from('App\Entity\Subject', 's')
                    ->where('s.active = 1')
                    ->andWhere('s.grade = :grade')
                    ->setParameter('grade', $learner->getGrade())
                    ->orderBy('s.name');

                $query = $queryBuilder->getQuery();
            } else {
                $queryBuilder = $this->em->createQueryBuilder();
                $query = $queryBuilder->select('s')
                    ->from('App\Entity\Subject', 's')
                    ->where('s.id NOT IN (:enrolledSubjectIds)')
                    ->andWhere('s.active = 1')
                    ->andWhere('s.grade = :grade')
                    ->setParameter('enrolledSubjectIds', $enrolledSubjectIds)
                    ->setParameter('grade', $learner->getGrade())
                    ->orderBy('s.name')
                    ->getQuery();
            }

            $subjects = $query->getResult();

            $subjectDetails = [];
            foreach ($subjects as $subject) {
                $queryBuilder = $this->em->createQueryBuilder();
                $queryBuilder->select('count(q.id)')
                    ->from('App\Entity\Question', 'q')
                    ->where('q.subject = :subject')
                    ->andWhere('q.status = \'approved\'')
                    ->andWhere('q.active = 1')
                    ->setParameter('subject', $subject);

                $totalQuestions = $queryBuilder->getQuery()->getSingleScalarResult();
                if ($totalQuestions > 0) {
                    $subjectDetails[] = [
                        'id' => $subject->getId(),
                        'name' => $subject->getName(),
                        'active' => $subject->isActive(),
                        'grade' => $subject->getGrade(),
                        'totalQuestions' => $totalQuestions
                    ];
                }
            }

            //push change

            return array(
                'status' => 'OK',
                'subjects' => $subjectDetails
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting subjects not enrolled by learner'
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
            $subjectId = $requestBody['subject_id'];

            if (empty($uid) || empty($subjectId)) {
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

            $subject = $this->em->getRepository(Subject::class)->find($subjectId);
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

    public function getLearnerSubjectPercentage(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $uid = $request->query->get('uid');
            $subjectId = $request->query->get('subject_id');

            if (empty($uid) || empty($subjectId)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'UID and Subject ID are required'
                );
            }

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            $subject = $this->em->getRepository(Subject::class)->find($subjectId);
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

            $totalQuestions = count($results);
            $correctAnswers = 0;

            foreach ($results as $result) {
                if ($result->getOutcome() === 'correct') {
                    $correctAnswers++;
                }
            }


            $learnerSubject = $this->em->getRepository(Learnersubjects::class)->findOneBy(['learner' => $learner, 'subject' => $subject]);

            if (!$learnerSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner subject not found'
                );
            }

            if (empty($results)) {

                $learnerSubject->setPercentage(0);
                $this->em->persist($learnerSubject);
                $this->em->flush();

                return array(
                    'status' => 'OK',
                    'percentage' => 0
                );
            }


            $percentage = ($correctAnswers / $totalQuestions);
            $learnerSubject->setPercentage($percentage);
            $this->em->persist($learnerSubject);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'learner_subject' => $learnerSubject,
                'percentage' => $percentage
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error calculating learner subject percentage'
            );
        }
    }

    public function setOverrideTerm(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $override = $requestBody['override'];

            if (empty($uid)) {
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


            $learner->setOverideTerm($override);
            $this->em->persist($learner);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully set override term'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error setting override term'
            );
        }
    }

    public function setHigherGradeFlag(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $learnerSubjectId = $requestBody['learner_subject_id'];
            $higherGrade = $requestBody['higher_grade'];

            if (empty($uid) || empty($learnerSubjectId)) {
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

            $learnerSubject = $this->em->getRepository(Learnersubjects::class)->findOneBy(['learner' => $learner, 'id' => $learnerSubjectId]);
            if (!$learnerSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner subject not found'
                );
            }

            $learnerSubject->setHigherGrade($higherGrade);
            $this->em->persist($learnerSubject);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully set higher grade flag'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error setting higher grade flag'
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

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/assets/images/learnMzansi';
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

            $question->setActive(0);
            $this->em->persist($question);
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
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'];
            $status = $requestBody['status'];
            $reviewerEmail = $requestBody['email'];
            $comment = $requestBody['comment'];

            if (empty($questionId) || empty($status) || empty($reviewerEmail)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'fields are required'
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
            $question->setReviewer($reviewerEmail);
            if (!empty($comment)) {
                $question->setComment($comment);
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

    public function removeSubjectFromLearner(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $uid = $requestBody['uid'];
            $subjectId = $requestBody['subject_id'];

            if (empty($uid) || empty($subjectId)) {
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

            $subject = $this->em->getRepository(Subject::class)->find($subjectId);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            $learnerSubject = $this->em->getRepository(Learnersubjects::class)->findOneBy(['learner' => $learner, 'subject' => $subject]);
            if (!$learnerSubject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner subject not found'
                );
            }

            // Remove all results for the learner and subject
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

            // Remove the learner subject
            $this->em->remove($learnerSubject);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully removed subject from learner and deleted related results'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error removing subject from learner'
            );
        }
    }

    public function logIssue(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true);
            $comment = $requestBody['comment'];
            $uid = $requestBody['uid'];
            $questionId = $requestBody['question_id'];

            if (empty($comment)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'comment is required'
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

            // Create a new Issue entity
            $issue = new Issue();
            $issue->setComment($comment);
            $issue->setLearner($learner);
            $issue->setCreated(new \DateTime());
            $issue->setQuestion($question);

            // Persist and flush the new entity
            $this->em->persist($issue);
            $this->em->flush();

            $this->logger->info("Logged new issue with ID {$issue->getId()}.");
            return array(
                'status' => 'OK',
                'message' => 'Successfully logged issue',
                'issue_id' => $issue->getId()
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error logging issue'
            );
        }
    }

    public function getAllActiveIssues(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $issues = $this->em->getRepository(Issue::class)->findBy(['status' => 'new']);
            return array(
                'status' => 'OK',
                'issues' => $issues
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting active issues'
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

    public function getQuestionsCapturedPerWeek(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Get start and end dates for the current week
            $startDate = new \DateTime('monday this week');
            $endDate = new \DateTime('sunday this week');
            $startDate->setTime(0, 0, 0);
            $endDate->setTime(23, 59, 59);

            // Create query builder
            $queryBuilder = $this->em->createQueryBuilder();
            $queryBuilder->select('q.capturer, COUNT(q.id) as questionCount')
                ->from('App\Entity\Question', 'q')
                ->where('q.created BETWEEN :startDate AND :endDate')
                ->groupBy('q.capturer')
                ->orderBy('questionCount', 'DESC')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);

            $results = $queryBuilder->getQuery()->getResult();

            // Format the results
            $formattedResults = [];
            foreach ($results as $result) {
                $formattedResults[] = [
                    'capturer' => $result['capturer'],
                    'count' => $result['questionCount'],
                    'week' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')
                ];
            }

            return array(
                'status' => 'OK',
                'data' => $formattedResults,
                'total_questions' => array_sum(array_column($formattedResults, 'count'))
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error getting questions captured per week'
            );
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
                ->setParameter('subject', $subject)
                ->setParameter('status', 'new')
                ->setParameter('currentId', $questionId)
                ->setParameter('active', true)
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
}
