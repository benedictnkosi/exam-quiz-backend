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
use App\Entity\ReportedMessages;
use App\Entity\RecordedQuestion;
use App\Entity\Message;
use App\Entity\Topic;

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
        string $openAiKey,
        private readonly PushNotificationService $pushNotificationService,
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
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $this->logger->info("Admin check passed");

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
            if (empty($data['type']) || empty($data['subject']) || empty($data['year']) || empty($data['term']) || (empty($data['answer']) && empty($data['answer_sheet'])) || empty($data['curriculum'])) {
                return array(
                    'status' => 'NOK',
                    'message' => "Missing required fields."
                );
            }

            //return an error if the capturer has more than 10 rejected questions
            $rejectedQuestions = $this->em->getRepository(Question::class)->findBy(['capturer' => $user->getId(), 'status' => 'rejected']);
            if (count($rejectedQuestions) >= 5 && $questionId == 0) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Cannot create new question - Please fix the errors in your rejected questions'
                );
            }

            //return an error if the capturer has more than 50 new questions
            $newQuestions = $this->em->getRepository(Question::class)->findBy(['capturer' => $user->getId(), 'status' => 'new']);
            if (count($newQuestions) >= 50 && $questionId == 0) {
                return array(
                    'status' => 'NOK',
                    'message' => 'You have more than 50 new questions - Please approve your new questions'
                );
            }


            // Validate that options are not empty for multiple_choice or multi_select types - fixed
            if (($data['type'] == 'multiple_choice' || $data['type'] == 'multi_select') && empty($data['answer_sheet'])) {
                if (empty($data['options']['option1']) || empty($data['options']['option2']) || empty($data['options']['option3']) || empty($data['options']['option4'])) {
                    return array(
                        'status' => 'NOK',
                        'message' => "Options cannot be empty for multiple_choice or multi_select types."
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
            if (!empty($data['answer'])) {
                $question->setAnswer($data['answer']);
            }
            $question->setOptions($data['options'] ?? null); // Pass the array directly
            $question->setTerm($data['term'] ?? null);
            $question->setExplanation($data['explanation'] ?? null);
            $question->setYear($data['year'] ?? null);
            $question->setCapturer($user);
            if ($questionId == 0) {
                $question->setReviewer($user);
                $question->setCreated(new \DateTime());
            }

            $question->setActive(true);
            $question->setStatus('new');
            $question->setComment("new");
            $question->setCurriculum($data['curriculum'] ?? "CAPS");
            $question->setUpdated(new \DateTime());

            //reset images
            $question->setImagePath('');
            $question->setQuestionImagePath('');
            $question->setAnswerImage('');
            $question->setOtherContextImages($data['otherContextImages'] ?? null);

            if (!empty($data['answer_sheet'])) {
                $question->setAnswerSheet(json_encode($data['answer_sheet']));
            }

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


    public function getRandomQuestionBySubjectName(
        string $subjectName,
        string $paperName,
        string $uid,
        int $questionId,
        string $platform = 'app',
        ?string $topic = null
    ) {
        try {
            // Get the learner first
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                );
            }

            if ($questionId !== 0) {
                $question = $this->em->getRepository(Question::class)->find($questionId);
                if (!$question) {
                    return array(
                        'status' => 'NOK',
                        'message' => 'Question not found'
                    );
                }
                // Remove answer before returning
                //$question->setAnswer(null);
                return $question;
            }

            // Get learner's terms and curriculum as arrays
            $learnerTerms = $learner->getTerms() ? array_map(function ($term) {
                return trim(str_replace('"', '', $term));
            }, explode(',', $learner->getTerms())) : [];

            $learnerCurriculum = $learner->getCurriculum() ? array_map(function ($curr) {
                return trim(str_replace('"', '', $curr));
            }, explode(',', $learner->getCurriculum())) : [];

            // Check if learner is admin
            $this->logger->info("Learner role: " . $learner->getRole());
            if ($learner->getRole() === 'admin') {
                // For admin, get their captured questions with 'new' status
                $this->logger->info("Getting admin questions for subject: " . $subjectName . ' ' . $paperName);
                $qb = $this->em->createQueryBuilder();
                $qb->select('q')
                    ->from('App\Entity\Question', 'q')
                    ->innerJoin('q.subject', 's', 'WITH', 's.name = :subjectName')
                    ->where('q.active = :active')
                    ->andWhere('q.status = :status')
                    ->andWhere('q.capturer = :capturer')
                    ->orderBy('q.created', 'DESC');

                $parameters = new ArrayCollection([
                    new Parameter('subjectName', $subjectName . ' ' . $paperName),
                    new Parameter('active', true),
                    new Parameter('status', 'new'),
                    new Parameter('capturer', $learner)
                ]);

                $qb->setParameters($parameters);

                $query = $qb->getQuery();
                $questions = $query->getResult();

                $this->logger->info("Found " . count($questions) . " new questions");

                if (!empty($questions)) {
                    $randomQuestion = $questions[0]; // Get the most recent new question
                    $this->logger->info("Selected question ID: " . $randomQuestion->getId() . " with status: " . $randomQuestion->getStatus());

                    // Double check the status
                    if ($randomQuestion->getStatus() !== 'new') {
                        $this->logger->error("Unexpected question status: " . $randomQuestion->getStatus() . " for question ID: " . $randomQuestion->getId());
                        return array(
                            'status' => 'NOK',
                            'message' => 'Invalid question status',
                            'context' => '',
                            'image_path' => ''
                        );
                    }
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
                // Remove answer before returning if platform is web
                if ($platform === 'web') {
                    $randomQuestion->setAnswer(null);
                }

                // Get related questions (same context and image path) that are also new
                $relatedQuestions = $this->getQuestionsWithSameContext($randomQuestion->getId());
                $relatedQuestionIds = $relatedQuestions['status'] === 'OK' ? $relatedQuestions['question_ids'] : [];
                if (!empty($relatedQuestionIds)) {
                    $relatedQb = $this->em->createQueryBuilder();
                    $relatedQb->select('q')
                        ->from('App\Entity\Question', 'q')
                        ->where('q.id IN (:ids)')
                        ->andWhere('q.status = :status')
                        ->setParameter('ids', $relatedQuestionIds)
                        ->setParameter('status', 'new')
                        ->setMaxResults(1);

                    $relatedQuestion = $relatedQb->getQuery()->getOneOrNullResult();
                    if ($relatedQuestion) {
                        $randomQuestion = $relatedQuestion;
                        $this->logger->info("Using related question ID: " . $randomQuestion->getId() . " with status: " . $randomQuestion->getStatus());
                    }
                }

                // Set related question IDs on the question object
                $relatedQuestionIds = array_values($relatedQuestionIds); // Reindex the array
                $randomQuestion->setRelatedQuestionIds($relatedQuestionIds);

                // Remove capturer, reviewer, and subject.capturer information
                $randomQuestion->setCapturer(null);
                $randomQuestion->setReviewer(null);
                if ($randomQuestion->getSubject()) {
                    $randomQuestion->getSubject()->setCapturer(null);
                    // Set topics to null to exclude them from the response
                    $randomQuestion->getSubject()->setTopics(null);
                }
                $this->logger->info("Final question ID: " . $randomQuestion->getId() . " with status: " . $randomQuestion->getStatus());

                return $randomQuestion;
            }

            if ($learner->getRole() === 'reviewer') {
                $qb = $this->em->createQueryBuilder();
                $qb->select('q')
                    ->from('App\Entity\Question', 'q')
                    ->join('q.subject', 's')
                    ->where('s.name = :subjectName')
                    ->andWhere('q.active = :active')
                    ->andWhere('q.status = :status')
                    ->andWhere('q.comment = :comment')
                    ->andWhere('s.grade = :grade');

                $qb->andWhere($qb->expr()->in('q.curriculum', ':curriculum'));
                $qb->andWhere($qb->expr()->in('q.term', ':terms'));

                $parameters = new ArrayCollection([
                    new Parameter('subjectName', $subjectName . ' ' . $paperName),
                    new Parameter('active', true),
                    new Parameter('status', 'approved'),
                    new Parameter('comment', 'new'),
                    new Parameter('grade', $learner->getGrade()),
                    new Parameter('terms', $learnerTerms),
                    new Parameter('curriculum', $learnerCurriculum)
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
                    // Remove answer before returning
                    $randomQuestion->setAnswer(null);
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
                ->where('s.name like :subjectName')
                ->andWhere('s.grade = :grade')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status');

            if ($topic) {
                // Join with Topic entity to filter by main topic
                $qb->leftJoin('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic AND t.subject = s')
                    ->andWhere('t.name = :mainTopic');
            }

            // Exclude mastered questions if any exist
            if (!empty($masteredQuestionIds)) {
                $qb->andWhere('q.id NOT IN (:masteredIds)');
            }

            // Add term condition if learner has terms specified
            if (!empty($learnerTerms)) {
                $qb->andWhere($qb->expr()->in('q.term', ':terms'));
            }

            // Add curriculum condition if learner has curriculum specified
            if (!empty($learnerCurriculum)) {
                $qb->andWhere($qb->expr()->in('q.curriculum', ':curriculum'));
            }

            // Set parameters
            $parameters = new ArrayCollection([
                new Parameter('grade', $grade),
                new Parameter('active', true),
                new Parameter('status', 'approved')
            ]);


            if ($topic) {
                $parameters->add(new Parameter('mainTopic', $topic));
                $parameters->add(new Parameter('subjectName', '%' . $subjectName . '%'));
            } else {
                $parameters->add(new Parameter('subjectName', '%' . $subjectName . ' ' . $paperName . '%'));
            }

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
            // Remove answer before returning if platform is web
            if ($platform === 'web') {
                $randomQuestion->setAnswer(null);
            }

            // Get related questions (same context and image path)
            $relatedQuestions = $this->getQuestionsWithSameContext($randomQuestion->getId(), $topic);
            $relatedQuestionIds = $relatedQuestions['status'] === 'OK' ? $relatedQuestions['question_ids'] : [];
            if (!empty($relatedQuestionIds)) {
                $randomQuestion = $this->em->getRepository(Question::class)->find($relatedQuestionIds[0]);
            }

            // Set related question IDs on the question object
            $relatedQuestionIds = array_values($relatedQuestionIds); // Reindex the array
            $randomQuestion->setRelatedQuestionIds($relatedQuestionIds);

            // Remove capturer, reviewer, and subject.capturer information
            $randomQuestion->setCapturer(null);
            $randomQuestion->setReviewer(null);
            if ($randomQuestion->getSubject()) {
                $randomQuestion->getSubject()->setCapturer(null);
                $randomQuestion->getSubject()->setTopics(null);
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

    public function getRecordingQuestion(string $subjectName, string $uid, int $grade, string $learnerTerms)
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

            // Get already recorded question IDs
            $recordedQuestionIds = $this->em->getRepository(RecordedQuestion::class)
                ->findRecordedQuestionIds();
            $recordedQuestionIds = array_map(function ($item) {
                return $item['questionId'];
            }, $recordedQuestionIds);

            // Build query with term and curriculum conditions
            $qb = $this->em->createQueryBuilder();
            $qb->select('q')
                ->from('App\Entity\Question', 'q')
                ->join('q.subject', 's')
                ->where('s.name = :subjectName')
                ->andWhere('s.grade = :grade')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status')
                ->andWhere('(q.imagePath IS NULL OR q.imagePath = :emptyString)')
                ->andWhere('(q.questionImagePath IS NULL OR q.questionImagePath = :emptyString)')
                ->andWhere('LENGTH(q.context) < 300')
                ->andWhere('LENGTH(q.question) < 300');

            // Exclude already recorded questions
            if (!empty($recordedQuestionIds)) {
                $qb->andWhere('q.id NOT IN (:recordedIds)');
            }

            // Add term condition if learner has terms specified
            if (!empty($learnerTerms)) {
                $qb->andWhere($qb->expr()->in('q.term', ':terms'));
            }

            // Add curriculum condition if learner has curriculum specified
            if (!empty($learnerCurriculum)) {
                $qb->andWhere($qb->expr()->in('q.curriculum', ':curriculum'));
            }

            // Set parameters
            $parameters = new ArrayCollection([
                new Parameter('subjectName', $subjectName),
                new Parameter('grade', $grade),
                new Parameter('active', true),
                new Parameter('status', 'approved'),
                new Parameter('emptyString', '')
            ]);

            if (!empty($recordedQuestionIds)) {
                $parameters->add(new Parameter('recordedIds', $recordedQuestionIds));
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
                // Get a random question
                $randomQuestion = $questions[array_rand($questions)];

                // Create a new RecordedQuestion entry
                $recordedQuestion = new RecordedQuestion();
                $recordedQuestion->setQuestionId($randomQuestion->getId());
                $recordedQuestion->setSubjectId($randomQuestion->getSubject()->getId());

                $this->em->persist($recordedQuestion);
                $this->em->flush();

                return $randomQuestion;
            }

            return array(
                'status' => 'NOK',
                'message' => 'No questions found'
            );

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
            $accounting = $request->query->get('accounting');

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
            $learnerTerms = $learner->getTerms() ? array_map(function ($term) {
                return trim(str_replace('"', '', $term));
            }, explode(',', $learner->getTerms())) : [];

            $learnerCurriculum = $learner->getCurriculum() ? array_map(function ($curr) {
                return trim(str_replace('"', '', $curr));
            }, explode(',', $learner->getCurriculum())) : [];

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
                        $qb->expr()->in('q.curriculum', ':curriculum'),
                        $qb->expr()->in('q.term', ':terms')
                    )
                )
                ->where('s.grade = :grade')
                ->andWhere('s.active = :subjectActive');

            // Add status filter only for non-admin users
            if ($learner->getRole() !== 'admin') {
                $qb->andWhere('q.status = :status');
            }

            if ($accounting === null) {
                $qb->andWhere('s.name NOT LIKE :accounting');
            }

            $qb->groupBy('s.id')
                ->orderBy('s.name', 'ASC');

            // Set parameters
            $parameters = new ArrayCollection([
                new Parameter('grade', $grade),
                new Parameter('active', true),
                new Parameter('subjectActive', true),
                new Parameter('curriculum', $learnerCurriculum),
                new Parameter('terms', $learnerTerms)
            ]);

            // Add status parameter only for non-admin users
            if ($learner->getRole() !== 'admin') {
                $parameters->add(new Parameter('status', 'approved'));
            }

            if ($accounting === null) {
                $parameters->add(new Parameter('accounting', '%Accounting%'));
            }

            $qb->setParameters($parameters);
            $subjects = $qb->getQuery()->getResult();

            $this->logger->info("subjects: " . count($subjects));
            // Get total results and correct answers for each subject
            foreach ($subjects as &$subject) {
                // Query for total results
                $this->logger->info("subject: " . $subject['name']);
                $resultsQb = $this->em->createQueryBuilder();
                $resultsQb->select('COUNT(r.id) as totalResults')
                    ->from('App\Entity\Result', 'r')
                    ->join('r.question', 'q')
                    ->where('r.learner = :learner')
                    ->andWhere('q.subject = :subject')
                    ->andWhere('q.active = :active');

                // Add status filter only for non-admin users
                if ($learner->getRole() !== 'admin') {
                    $resultsQb->andWhere('q.status = :status');
                }

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
                    new Parameter('active', true)
                ]);

                // Add status parameter only for non-admin users
                if ($learner->getRole() !== 'admin') {
                    $resultParameters->add(new Parameter('status', 'approved'));
                }

                if (!empty($learnerTerms)) {
                    $resultParameters->add(new Parameter('terms', $learnerTerms));
                }

                if (!empty($learnerCurriculum)) {
                    $resultParameters->add(new Parameter('curriculum', $learnerCurriculum));
                }

                $resultsQb->setParameters($resultParameters);
                $totalResults = $resultsQb->getQuery()->getSingleScalarResult();
                $subject['totalResults'] = $totalResults;

                // Query for correct answers count
                $correctAnswersQb = $this->em->createQueryBuilder();
                $correctAnswersQb->select('COUNT(r.id) as correctCount')
                    ->from('App\Entity\Result', 'r')
                    ->join('r.question', 'q')
                    ->where('r.learner = :learner')
                    ->andWhere('q.subject = :subject')
                    ->andWhere('q.active = :active')
                    ->andWhere('r.outcome = :outcome');

                // Add status filter only for non-admin users
                if ($learner->getRole() !== 'admin') {
                    $correctAnswersQb->andWhere('q.status = :status');
                }

                // Add term and curriculum conditions
                if (!empty($learnerTerms)) {
                    $correctAnswersQb->andWhere('q.term IN (:terms)');
                }
                if (!empty($learnerCurriculum)) {
                    $correctAnswersQb->andWhere('q.curriculum IN (:curriculum)');
                }

                $correctParameters = clone $resultParameters;
                $correctParameters->add(new Parameter('outcome', 'correct'));

                $correctAnswersQb->setParameters($correctParameters);
                $correctAnswers = $correctAnswersQb->getQuery()->getSingleScalarResult();
                $subject['correctAnswers'] = $correctAnswers;
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

    public function removeLearnerResultsBySubject(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $requestBody = json_decode($request->getContent(), true) ?? [];

            // Try to get values from both JSON body and query parameters
            $uid = $requestBody['uid'] ?? $request->query->get('uid');
            $subjectName = $requestBody['subject_name'] ?? $request->query->get('subject_name');

            if (empty($uid) || empty($subjectName)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing: uid and subject_name are required'
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
            } elseif ($imageType == 'other_context') {
                //$question->setOtherContextImages($newFilename);
                $this->logger->info("Other context images: " . $newFilename);
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

    public function uploadChatFile(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $file = $request->files->get('file');
            if (!$file) {
                return array(
                    'status' => 'NOK',
                    'message' => 'No file provided'
                );
            }

            // Validate file size (5MB limit)
            $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
            if ($file->getSize() > $maxFileSize) {
                return array(
                    'status' => 'NOK',
                    'message' => 'File size exceeds the maximum limit of 5MB'
                );
            }

            // Create upload directory if it doesn't exist
            $uploadDir = $this->projectDir . '/public/assets/chat';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Validate file
            // if (!$file->isValid()) {
            //     return array(
            //         'status' => 'NOK',
            //         'message' => 'Invalid file upload'
            //     );
            // }

            // Generate unique filename
            $newFilename = uniqid() . '.' . $file->guessExtension();
            $this->logger->info("Attempting to upload file: $newFilename");

            // Move file to upload directory
            $file->move($uploadDir, $newFilename);
            $this->logger->info("File successfully uploaded: $newFilename");

            return array(
                'status' => 'OK',
                'message' => 'File successfully uploaded',
                'fileName' => $newFilename,
                'filePath' => '/assets/chat/' . $newFilename
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error uploading file: ' . $e->getMessage()
            );
        }
    }
    public function uploadLectureImage(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $file = $request->files->get('file');
            $topicId = $request->request->get('topic_id');

            if (!$file) {
                return array(
                    'status' => 'NOK',
                    'message' => 'No file provided'
                );
            }

            if (!$topicId) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Topic ID is required'
                );
            }

            // Validate file size (5MB limit)
            $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
            if ($file->getSize() > $maxFileSize) {
                return array(
                    'status' => 'NOK',
                    'message' => 'File size exceeds the maximum limit of 5MB'
                );
            }

            // Create upload directory if it doesn't exist
            $uploadDir = $this->projectDir . '/public/assets/images/lectures';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $newFilename = uniqid() . '.' . $file->guessExtension();
            $this->logger->info("Attempting to upload file: $newFilename");

            // Move file to upload directory
            $file->move($uploadDir, $newFilename);
            $this->logger->info("File successfully uploaded: $newFilename");

            // Update topic image file name
            $topic = $this->em->getRepository(Topic::class)->find($topicId);
            if (!$topic) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Topic not found'
                );
            }

            $topic->setImageFileName($newFilename);
            $this->em->persist($topic);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'File successfully uploaded and topic image updated',
                'fileName' => $newFilename,
                'filePath' => '/assets/images/lectures/' . $newFilename
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error uploading file: ' . $e->getMessage()
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
            $imageType = $requestBody['image_type'];

            if (empty($questionId) || empty($imageName) || empty($imageType)) {
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

            if ($imageType == 'question_context') {
                $question->setImagePath($imageName);
            } elseif ($imageType == 'question') {
                $question->setQuestionImagePath($imageName);
            } elseif ($imageType == 'answer') {
                $question->setAnswerImage($imageName);
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
                    'message' => 'Grade and Subject are required'
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

            //only learner with role or reviewer can approve a question
            if ($learner->getRole() !== 'admin' && $learner->getRole() !== 'reviewer' && $status === 'approved') {
                return array(
                    'status' => 'NOK',
                    'message' => 'Only admin or reviewer can approve a question'
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

            if ($status == 'approved' && $learner->getRole() == 'reviewer') {
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
    public function getRejectedQuestionsByCapturer(string $uid): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            if (empty($uid)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Capturer is required'
                ];
            }

            $capturer = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$capturer) {
                return [
                    'status' => 'NOK',
                    'message' => 'Capturer not found'
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
        $questionId = $request->query->get('id');
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
    public function convertImagesToText($request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $imageName = $request->query->get('image_name');
        try {
            $imageUrl = "https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=" . $imageName;

            $this->logger->info("Image URL: " . $imageUrl);
            $data = [
                "model" => "gpt-4o-mini",
                "messages" => [
                    [
                        "role" => "system",
                        "content" => "You are an AI that converts images to text. Return the text only. Do not include any other text or comments"
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
                    $text = $result['choices'][0]['message']['content'];
                    return array(
                        'status' => 'OK',
                        'message' => $text
                    );
                } else {
                    return array(
                        'status' => 'NOK',
                        'message' => 'Error processing question images 1'
                    );
                }
            } else {
                return array(
                    'status' => 'NOK',
                    'message' => 'Error processing question images 2',
                    'response' => $response
                );
            }

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
                    "content" => "You are an AI tutor for students aged 13, that creates lessons to questions based on their context. Follow these rules:\n1. Read the provided context and analyze any accompanying images.\n2. Understand the question and the correct answer.\n3. Format the explanation as **bullet points**.\n4. reference the context and images in your explanation.\n5 have headings in your explanation. make it long as detailed. \n6 at the end, add a small bite size key lesson, prefixed with ***Key Lesson:***. less than 20 words. \n7 make the lesson fun and add emojis where suitable."
                ],
                [
                    "role" => "user",
                    "content" => []
                ]
            ];

            // Add question context
            $messages[1]['content'][] = [
                "type" => "text",
                "text" => "Context: " . ($question->getContext())
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
            if ($question->getImagePath() && $question->getImagePath() != "" && $question->getImagePath() != null && $question->getImagePath() != "NULL") {
                $imageUrl = "https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=" . $question->getImagePath();
                $messages[1]['content'][] = [
                    "type" => "image_url",
                    "image_url" => ["url" => $imageUrl]
                ];
            }

            //add image for questionImagePath
            if ($question->getQuestionImagePath() && $question->getQuestionImagePath() != "" && $question->getQuestionImagePath() != null && $question->getQuestionImagePath() != "NULL") {
                $imageUrl = "https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=" . $question->getQuestionImagePath();
                $messages[1]['content'][] = [
                    "type" => "image_url",
                    "image_url" => ["url" => $imageUrl]
                ];
            }

            //add other context images if they exist
            $otherContextImages = $question->getOtherContextImages();
            if ($otherContextImages && !empty($otherContextImages)) {
                foreach ($otherContextImages as $imagePath) {
                    if ($imagePath && $imagePath != "" && $imagePath != null && $imagePath != "NULL") {
                        $imageUrl = "https://examquiz.dedicated.co.za/public/learn/learner/get-image?image=" . $imagePath;
                        $messages[1]['content'][] = [
                            "type" => "image_url",
                            "image_url" => ["url" => $imageUrl]
                        ];
                    }
                }
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

            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if ($learner) {
                if ($learner->getRole() == 'admin') {
                    return array(
                        'status' => 'OK',
                        'message' => 'Successfully created learner'
                    );
                }
            }

            $grade = $requestBody['grade'];
            $terms = $requestBody['terms'];
            $curriculum = $requestBody['curriculum'];
            $schoolName = $requestBody['school_name'] ?? null;
            $schoolAddress = $requestBody['school_address'] ?? null;
            $email = $requestBody['email'];

            if (empty($uid) || empty($terms) || empty($curriculum)) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Mandatory values missing'
                );
            }

            $cleanCurriculum = '';
            if (!empty($requestBody['curriculum'])) {
                // Remove quotes and clean the string
                $curriculum = str_replace(['"', "'"], '', $requestBody['curriculum']);
                $curriculumArray = array_map('trim', explode(',', $curriculum));
                $cleanCurriculum = implode(',', $curriculumArray);
            }


            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                //new user
                $learner = new Learner();
                $learner->setUid($uid);
                $learner->setPoints(0);
                $learner->setCreated(created: new \DateTime());
                $learner->setCurriculum(curriculum: "IEB,CAPS");
                $learner->setNewThreadNotification(1);
                if ($curriculum == "IEB") {
                    $learner->setPrivateSchool(true);
                } else {
                    $learner->setPrivateSchool(false);
                }
                if (!empty($email)) {
                    $learner->setEmail($email);
                }

                // Generate random 4-letter code starting with first letter of name
                $firstLetter = strtoupper(substr($name, 0, 1));
                $randomLetters = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
                $followMeCode = $firstLetter . $randomLetters;

                // Check if code is already in use
                $existingLearner = $this->em->getRepository(Learner::class)->findOneBy(['followMeCode' => $followMeCode]);
                $attempts = 0;
                while ($existingLearner && $attempts < 10) {
                    $randomLetters = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
                    $followMeCode = $firstLetter . $randomLetters;
                    $existingLearner = $this->em->getRepository(Learner::class)->findOneBy(['followMeCode' => $followMeCode]);
                    $attempts++;
                }

                if ($existingLearner) {
                    return array(
                        'status' => 'NOK',
                        'message' => 'Unable to generate a unique follow code. Please try again.'
                    );
                }

                $learner->setFollowMeCode($followMeCode);
            } else {
                //if learner is admin
                if ($learner->getRole() == 'admin') {
                    return array(
                        'status' => 'OK',
                        'message' => 'Successfully created learner'
                    );
                }
                $learner->setCurriculum($cleanCurriculum);
            }

            if ($name) {
                $learner->setName($name);
            }

            $learner->setLastSeen(new \DateTime());
            $grade = $this->em->getRepository(Grade::class)->findOneBy(['number' => $grade]);
            if ($grade) {
                if ($grade !== $learner->getGrade()) {
                    $results = $this->em->getRepository(Result::class)->findBy(['learner' => $learner]);
                    foreach ($results as $result) {
                        $this->em->remove($result);
                    }
                    //reset points
                    $learner->setPoints(0);
                    $this->em->flush();
                }
            } else {
                return array(
                    'status' => 'NOK',
                    'message' => 'Grade not found'
                );
            }

            // Clean and format terms and curriculum
            $cleanTerms = '';
            if (!empty($requestBody['terms'])) {
                // Remove quotes and clean the string
                $terms = str_replace(['"', "'"], '', $requestBody['terms']);
                $termsArray = array_map('trim', explode(',', $terms));
                $cleanTerms = implode(',', $termsArray);
            }

            $learner->setGrade($grade);
            $learner->setNotificationHour(18);
            $learner->setTerms($cleanTerms);

            // Handle optional school details
            if ($schoolName) {
                $learner->setSchoolName(substr($schoolName, 0, 255));
            }

            if ($schoolAddress) {
                $learner->setSchoolAddress(substr($schoolAddress, 0, 255));
            }

            $learner->setSchoolLatitude($requestBody['school_latitude'] ?? null);
            $learner->setSchoolLongitude($requestBody['school_longitude'] ?? null);
            $learner->setAvatar($requestBody['avatar'] ?? null);




            $this->em->persist($learner);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully created learner'
            );

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error creating learner ' . $e->getMessage()
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
            $uid = $request->query->get('uid');

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



    public function getQuestionStatusCountsByCapturers(Request $request): array
    {
        try {
            $fromDate = $request->query->get('from_date');
            if (!$fromDate) {
                $fromDate = (new \DateTime())->modify('-4 weeks')->format('Y-m-d');
            }

            $this->logger->info('fromDate: ' . $fromDate);

            $qb = $this->em->createQueryBuilder();

            // Get new questions count
            $qbNew = $this->em->createQueryBuilder();
            $qbNew->select('l.name as capturer_name, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.capturer', 'l')
                ->where('q.status = :statusNew')
                ->andWhere('q.created >= :fromDate')
                ->groupBy('l.id, l.name')
                ->orderBy('l.name', 'ASC');

            // Get approved questions count
            $qbApproved = $this->em->createQueryBuilder();
            $qbApproved->select('l.name as capturer_name, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.capturer', 'l')
                ->where('q.status = :statusApproved')
                ->andWhere('q.created >= :fromDate')
                ->groupBy('l.id, l.name')
                ->orderBy('l.name', 'ASC');

            // Get rejected questions count
            $qbRejected = $this->em->createQueryBuilder();
            $qbRejected->select('l.name as capturer_name, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.capturer', 'l')
                ->where('q.status = :statusRejected')
                ->andWhere('q.created >= :fromDate')
                ->groupBy('l.id, l.name')
                ->orderBy('l.name', 'ASC');

            // Execute queries
            $newResults = $qbNew->getQuery()
                ->setParameter('statusNew', 'new')
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->getResult();

            $approvedResults = $qbApproved->getQuery()
                ->setParameter('statusApproved', 'approved')
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->getResult();

            $rejectedResults = $qbRejected->getQuery()
                ->setParameter('statusRejected', 'rejected')
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->getResult();

            // Combine results
            $combinedResults = [];
            foreach ($newResults as $result) {
                $name = $result['capturer_name'];
                if (!isset($combinedResults[$name])) {
                    $combinedResults[$name] = [
                        'capturer' => $name,
                        'new' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                }
                $combinedResults[$name]['new'] = (int) $result['count'];
            }

            foreach ($approvedResults as $result) {
                $name = $result['capturer_name'];
                if (!isset($combinedResults[$name])) {
                    $combinedResults[$name] = [
                        'capturer' => $name,
                        'new' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                }
                $combinedResults[$name]['approved'] = (int) $result['count'];
            }

            foreach ($rejectedResults as $result) {
                $name = $result['capturer_name'];
                if (!isset($combinedResults[$name])) {
                    $combinedResults[$name] = [
                        'capturer' => $name,
                        'new' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                }
                $combinedResults[$name]['rejected'] = (int) $result['count'];
            }

            // Add total and convert to array
            $finalResults = [];
            foreach ($combinedResults as $result) {
                $result['total'] = $result['new'] + $result['approved'] + $result['rejected'];
                $finalResults[] = $result;
            }

            return [
                'status' => 'OK',
                'data' => $finalResults
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting question status counts'
            ];
        }
    }

    public function getQuestionsReviewedByLearner(Request $request): array
    {
        try {
            $fromDate = $request->query->get('from_date');
            if (!$fromDate) {
                $fromDate = (new \DateTime())->modify('-4 weeks')->format('Y-m-d');
            }

            $qb = $this->em->createQueryBuilder();
            $qb->select('l.name as reviewer_name, q.status, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.reviewer', 'l')
                ->where('q.created >= :fromDate')
                ->andWhere('q.status IN (:statuses)')
                ->groupBy('l.id, l.name, q.status')
                ->orderBy('l.name', 'ASC')
                ->addOrderBy('q.status', 'ASC');

            $results = $qb->getQuery()
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->setParameter('statuses', ['approved', 'rejected'])
                ->getResult();

            // Format the results
            $formattedResults = [];
            foreach ($results as $result) {
                $reviewerName = $result['reviewer_name'];
                if (!isset($formattedResults[$reviewerName])) {
                    $formattedResults[$reviewerName] = [
                        'reviewer' => $reviewerName,
                        'approved' => 0,
                        'rejected' => 0,
                        'new' => 0,
                        'total' => 0
                    ];
                }

                if ($result['status'] === 'approved') {
                    $formattedResults[$reviewerName]['approved'] = (int) $result['count'];
                } else if ($result['status'] === 'rejected') {
                    $formattedResults[$reviewerName]['rejected'] = (int) $result['count'];
                } else if ($result['status'] === 'new') {
                    $formattedResults[$reviewerName]['new'] = (int) $result['count'];
                }

                $formattedResults[$reviewerName]['total'] =
                    $formattedResults[$reviewerName]['approved'] +
                    $formattedResults[$reviewerName]['rejected'] +
                    $formattedResults[$reviewerName]['new'];
            }

            // Convert to indexed array
            $finalResults = array_values($formattedResults);

            return [
                'status' => 'OK',
                'data' => $finalResults
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting reviewed questions count'
            ];
        }
    }

    /**
     * Delete a question by ID (admin only)
     */
    public function deleteQuestion(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'] ?? null;

            if (!$questionId) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question ID is required'
                ];
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            // Begin transaction
            $this->em->beginTransaction();
            try {
                // Delete associated results first
                $results = $this->em->getRepository(Result::class)->findBy(['question' => $question]);
                foreach ($results as $result) {
                    $this->em->remove($result);
                }

                // Delete the question
                $this->em->remove($question);
                $this->em->flush();
                $this->em->commit();

                return [
                    'status' => 'OK',
                    'message' => 'Question and associated data deleted successfully'
                ];

            } catch (\Exception $e) {
                $this->em->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error deleting question: ' . $e->getMessage()
            ];
        }
    }

    public function getQuestionStats(Request $request): array
    {
        try {
            $fromDate = $request->query->get('fromDate');
            $endDate = $request->query->get('endDate');
            if (!$fromDate) {
                $fromDate = (new \DateTime())->modify('-4 weeks')->format('Y-m-d');
            }

            if (!$endDate) {
                $endDate = (new \DateTime())->format('Y-m-d');
            }

            $this->logger->info('Date range: ' . $fromDate . ' to ' . $endDate);

            // Get new questions count
            $qbNew = $this->em->createQueryBuilder();
            $qbNew->select('l.name as capturer_name, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.capturer', 'l')
                ->where('q.status = :statusNew')
                ->andWhere('q.created >= :fromDate')
                ->andWhere('q.created <= :endDate')
                ->groupBy('l.id, l.name')
                ->orderBy('l.name', 'ASC');

            // Get approved questions count
            $qbApproved = $this->em->createQueryBuilder();
            $qbApproved->select('l.name as capturer_name, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.capturer', 'l')
                ->where('q.status = :statusApproved')
                ->andWhere('q.created >= :fromDate')
                ->andWhere('q.created <= :endDate')
                ->groupBy('l.id, l.name')
                ->orderBy('l.name', 'ASC');

            // Get rejected questions count
            $qbRejected = $this->em->createQueryBuilder();
            $qbRejected->select('l.name as capturer_name, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.capturer', 'l')
                ->where('q.status = :statusRejected')
                ->andWhere('q.created >= :fromDate')
                ->andWhere('q.created <= :endDate')
                ->groupBy('l.id, l.name')
                ->orderBy('l.name', 'ASC');

            // Execute queries with both date parameters
            $newResults = $qbNew->getQuery()
                ->setParameter('statusNew', 'new')
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'))
                ->getResult();

            $approvedResults = $qbApproved->getQuery()
                ->setParameter('statusApproved', 'approved')
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'))
                ->getResult();

            $rejectedResults = $qbRejected->getQuery()
                ->setParameter('statusRejected', 'rejected')
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'))
                ->getResult();

            // Combine results
            $combinedResults = [];
            foreach ($newResults as $result) {
                $name = $result['capturer_name'];
                if (!isset($combinedResults[$name])) {
                    $combinedResults[$name] = [
                        'capturer' => $name,
                        'new' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                }
                $combinedResults[$name]['new'] = (int) $result['count'];
            }

            foreach ($approvedResults as $result) {
                $name = $result['capturer_name'];
                if (!isset($combinedResults[$name])) {
                    $combinedResults[$name] = [
                        'capturer' => $name,
                        'new' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                }
                $combinedResults[$name]['approved'] = (int) $result['count'];
            }

            foreach ($rejectedResults as $result) {
                $name = $result['capturer_name'];
                if (!isset($combinedResults[$name])) {
                    $combinedResults[$name] = [
                        'capturer' => $name,
                        'new' => 0,
                        'approved' => 0,
                        'rejected' => 0
                    ];
                }
                $combinedResults[$name]['rejected'] = (int) $result['count'];
            }

            // Add total and convert to array
            $finalResults = [];
            foreach ($combinedResults as $result) {
                $result['total'] = $result['new'] + $result['approved'] + $result['rejected'];
                $finalResults[] = $result;
            }

            return [
                'status' => 'OK',
                'data' => $finalResults,
                'date_range' => [
                    'from' => $fromDate,
                    'to' => $endDate
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting question statistics'
            ];
        }
    }

    public function getReviewerStats(Request $request): array
    {
        try {
            $fromDate = $request->query->get('fromDate');
            $endDate = $request->query->get('endDate');

            if (!$fromDate) {
                $fromDate = (new \DateTime())->modify('-4 weeks')->format('Y-m-d');
            }

            if (!$endDate) {
                $endDate = (new \DateTime())->format('Y-m-d');
            }

            $this->logger->info('Date range: ' . $fromDate . ' to ' . $endDate);

            $qb = $this->em->createQueryBuilder();
            $qb->select('l.name as reviewer_name, q.status, COUNT(q.id) as count')
                ->from('App\Entity\Question', 'q')
                ->join('q.reviewer', 'l')
                ->where('q.created >= :fromDate')
                ->andWhere('q.created <= :endDate')
                ->andWhere('q.status IN (:statuses)')
                ->groupBy('l.id, l.name, q.status')
                ->orderBy('l.name', 'ASC')
                ->addOrderBy('q.status', 'ASC');

            $results = $qb->getQuery()
                ->setParameter('fromDate', new \DateTime($fromDate))
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'))
                ->setParameter('statuses', ['approved', 'rejected'])
                ->getResult();

            // Format the results
            $formattedResults = [];
            foreach ($results as $result) {
                $reviewerName = $result['reviewer_name'];
                if (!isset($formattedResults[$reviewerName])) {
                    $formattedResults[$reviewerName] = [
                        'reviewer' => $reviewerName,
                        'approved' => 0,
                        'rejected' => 0,
                        'total' => 0,
                        'approval_rate' => 0
                    ];
                }

                if ($result['status'] === 'approved') {
                    $formattedResults[$reviewerName]['approved'] = (int) $result['count'];
                } else if ($result['status'] === 'rejected') {
                    $formattedResults[$reviewerName]['rejected'] = (int) $result['count'];
                }
            }

            // Calculate totals and approval rates
            foreach ($formattedResults as &$reviewer) {
                $reviewer['total'] = $reviewer['approved'] + $reviewer['rejected'];
                $reviewer['approval_rate'] = $reviewer['total'] > 0
                    ? round(($reviewer['approved'] / $reviewer['total']) * 100, 2)
                    : 0;
            }

            // Convert to indexed array and sort by total reviews
            $finalResults = array_values($formattedResults);
            usort($finalResults, function ($a, $b) {
                return $b['total'] - $a['total'];
            });

            return [
                'status' => 'OK',
                'data' => $finalResults,
                'date_range' => [
                    'from' => $fromDate,
                    'to' => $endDate
                ],
                'summary' => [
                    'total_reviewers' => count($finalResults),
                    'total_reviews' => array_sum(array_column($finalResults, 'total')),
                    'total_approved' => array_sum(array_column($finalResults, 'approved')),
                    'total_rejected' => array_sum(array_column($finalResults, 'rejected'))
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting reviewer statistics'
            ];
        }
    }


    public function getSmallestImage(): ?string
    {
        $smallestImage = null;
        $smallestSize = PHP_INT_MAX;
        $projectDir = $this->getParameter('kernel.project_dir');
        $projectDir = '../public/assets/images/learnMzansi'; // Initialize here

        $files = glob($projectDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

        foreach ($files as $file) {
            $this->logger->info('File: ' . $file);
            $fileSize = filesize($file);
            if ($fileSize < $smallestSize) {
                $smallestSize = $fileSize;
                $smallestImage = $file;
            }
        }

        return $smallestImage ? basename($smallestImage) : null;
    }

    public function getSmallestImages($request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $limit = $request->query->get('limit', 10);

        $projectDir = $this->getParameter('kernel.project_dir');
        $projectDir = '../public/assets/images/learnMzansi'; // Initialize here

        $files = glob($projectDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $images = [];

        foreach ($files as $file) {
            $fileSize = filesize($file);
            $images[] = ['path' => $file, 'size' => $fileSize];
        }

        // Sort images by size
        usort($images, function ($a, $b) {
            return $a['size'] <=> $b['size'];
        });

        // Get the 10 smallest images
        $smallestImages = array_slice($images, 0, $limit);

        $result = [];
        foreach ($smallestImages as $image) {
            $imageName = basename($image['path']);

            // Check both imagePath and questionImagePath
            $questions = $this->em->getRepository(Question::class)->createQueryBuilder('q')
                ->where('q.imagePath = :imageName OR q.questionImagePath = :imageName')
                ->andWhere('q.status = :status')
                ->setParameter('imageName', $imageName)
                ->setParameter('status', 'approved')
                ->getQuery()
                ->getResult();

            foreach ($questions as $question) {
                $result[] = [
                    'image' => $imageName,
                    'question' => $question->getId()
                ];
            }
        }

        return [
            'status' => 'OK',
            'images' => $result
        ];
    }

    public function getRandomQuestionWithRevision(Request $request): mixed
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $subjectName = $request->query->get('subject_name');
            $paperName = $request->query->get('paper_name');
            $uid = $request->query->get('uid');
            $isRevision = $request->query->get('revision', false);
            $topic = $request->query->get('topic');

            if (empty($subjectName) || empty($uid)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Subject name and user ID are required'
                ];
            }

            // Get the learner with a single query
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Get learner's grade
            $grade = $learner->getGrade();
            if (!$grade) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner grade not found'
                ];
            }

            // Handle topic tracking
            $excludedQuestionIds = [];
            $topicLessonsTracker = $learner->getTopicLessonsTracker();

            if ($topic && $topicLessonsTracker) {
                // Check if the topic exists in the tracker
                if (isset($topicLessonsTracker[$topic])) {
                    // Get all question IDs for this topic
                    $excludedQuestionIds = $topicLessonsTracker[$topic];
                } else {
                    // If topic doesn't exist, reset the tracker
                    $topicLessonsTracker = [];
                    $learner->setTopicLessonsTracker($topicLessonsTracker);
                    $this->em->flush();
                }
            }

            // Build optimized query to get a random question
            $qb = $this->em->createQueryBuilder();
            $qb->select('q')
                ->from('App\Entity\Question', 'q')
                ->innerJoin('q.subject', 's')
                ->where('s.grade = :grade')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status');

            if ($topic) {
                $qb->leftJoin('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic AND t.subject = s')
                    ->andWhere('t.name = :mainTopic')
                    ->andWhere('s.name LIKE :subjectName')
                    ->setParameter('mainTopic', $topic)
                    ->setParameter('subjectName', '%' . $subjectName . '%');
            } else {
                $qb->andWhere('s.name = :subjectName')
                    ->setParameter('subjectName', $subjectName . ' ' . $paperName);
            }

            // Exclude previously viewed questions if any
            if (!empty($excludedQuestionIds)) {
                $qb->andWhere('q.id NOT IN (:excludedIds)')
                    ->setParameter('excludedIds', $excludedQuestionIds);
            }

            $qb->setParameter('grade', $grade)
                ->setParameter('active', true)
                ->setParameter('status', 'approved');

            // Get count of matching questions
            $countQb = clone $qb;
            $count = $countQb->select('COUNT(q.id)')->getQuery()->getSingleScalarResult();

            if ($count === 0) {
                // If we have excluded questions and found nothing, reset the tracker and try again
                if (!empty($excludedQuestionIds) && $topic) {
                    $topicLessonsTracker = $learner->getTopicLessonsTracker() ?? [];
                    $topicLessonsTracker[$topic] = [];
                    $learner->setTopicLessonsTracker($topicLessonsTracker);
                    $this->em->flush();

                    // Try the query again without exclusions
                    $qb = $this->em->createQueryBuilder();
                    $qb->select('q')
                        ->from('App\Entity\Question', 'q')
                        ->innerJoin('q.subject', 's')
                        ->where('s.grade = :grade')
                        ->andWhere('q.active = :active')
                        ->andWhere('q.status = :status');

                    if ($topic) {
                        $qb->leftJoin('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic AND t.subject = s')
                            ->andWhere('t.name = :mainTopic')
                            ->andWhere('s.name LIKE :subjectName')
                            ->setParameter('mainTopic', $topic)
                            ->setParameter('subjectName', '%' . $subjectName . '%');
                    } else {
                        $qb->andWhere('s.name = :subjectName')
                            ->setParameter('subjectName', $subjectName . ' ' . $paperName);
                    }

                    $qb->setParameter('grade', $grade)
                        ->setParameter('active', true)
                        ->setParameter('status', 'approved');

                    // Get count again
                    $countQb = clone $qb;
                    $count = $countQb->select('COUNT(q.id)')->getQuery()->getSingleScalarResult();

                    if ($count === 0) {
                        return [
                            'status' => 'NOK',
                            'message' => 'No questions available'
                        ];
                    }
                } else {
                    return [
                        'status' => 'NOK',
                        'message' => 'No questions available'
                    ];
                }
            }

            // Get random offset
            $offset = random_int(0, $count - 1);

            // Get single random question
            $randomQuestion = $qb->setFirstResult($offset)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$randomQuestion) {
                return [
                    'status' => 'NOK',
                    'message' => 'Failed to get random question'
                ];
            }

            // Update topic tracker if a topic is specified
            if ($topic) {
                $topicLessonsTracker = $learner->getTopicLessonsTracker() ?? [];

                if (!isset($topicLessonsTracker[$topic])) {
                    $topicLessonsTracker[$topic] = [];
                }

                // Add the question ID to the tracker
                $topicLessonsTracker[$topic][] = $randomQuestion->getId();
                $learner->setTopicLessonsTracker($topicLessonsTracker);
                $this->em->flush();
            }

            // Shuffle options if they exist
            $options = $randomQuestion->getOptions();
            if ($options) {
                shuffle($options);
                $randomQuestion->setOptions($options);
            }

            // Check if question has AI explanation
            if (!$randomQuestion->getAiExplanation()) {
                $aiExplanationResult = $this->getAIExplanation($randomQuestion->getId());
                if ($aiExplanationResult['status'] === 'OK') {
                    $randomQuestion->setAiExplanation($aiExplanationResult['explanation']);
                    $this->em->flush();
                }
            }

            // Clear unnecessary relations
            $randomQuestion->setCapturer(null);
            $randomQuestion->setReviewer(null);
            if ($randomQuestion->getSubject()) {
                $randomQuestion->getSubject()->setCapturer(null);
                $randomQuestion->getSubject()->setTopics(null);
            }

            return $randomQuestion;

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting random question: ' . $e->getMessage()
            ];
        }
    }

    public function getRandomQuestionWithAIExplanation(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $uid = $request->query->get('uid');
            $subjectName = $request->query->get('subject_name');

            if (empty($uid)) {
                return [
                    'status' => 'NOK',
                    'message' => 'User ID is required'
                ];
            }

            // Get the learner
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Get the learner's grade
            $grade = $learner->getGrade();
            if (!$grade) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner grade not found'
                ];
            }

            // Build query for questions with valid AI explanations
            $qb = $this->em->createQueryBuilder();
            $qb->select('q')
                ->from(Question::class, 'q')
                ->join('q.subject', 's')
                ->where('q.aiExplanation IS NOT NULL')
                ->andWhere('q.aiExplanation != :emptyString')
                ->andWhere('q.aiExplanation != :nullString')
                ->andWhere('s.grade = :grade')
                ->andWhere('q.aiExplanation NOT LIKE :dollarSign')
                ->andWhere('q.aiExplanation NOT LIKE :curlyBraces')
                ->andWhere('q.aiExplanation LIKE :keyLesson')
                ->setParameter('emptyString', '')
                ->setParameter('nullString', 'NULL')
                ->setParameter('grade', $grade)
                ->setParameter('dollarSign', '%$%')
                ->setParameter('curlyBraces', '%{%')
                ->setParameter('keyLesson', '%Key Lesson%');

            // If subject ID is provided, filter by that subject
            if (!empty($subjectName)) {
                $qb->andWhere('s.name like :subjectName')
                    ->setParameter('subjectName', '%' . $subjectName . '%');
            } else {
                // Get subjects the learner has answered
                $subjectsQb = $this->em->createQueryBuilder();
                $subjectsQb->select('DISTINCT s.id')
                    ->from(Result::class, 'r')
                    ->join('r.question', 'q')
                    ->join('q.subject', 's')
                    ->where('r.learner = :learner')
                    ->setParameter('learner', $learner);

                $subjectIds = $subjectsQb->getQuery()->getSingleColumnResult();

                // If learner has answered questions, prioritize those subjects
                if (!empty($subjectIds)) {
                    $qb->andWhere('s.id IN (:subjectIds)')
                        ->setParameter('subjectIds', $subjectIds);
                }
            }

            $questions = $qb->getQuery()->getResult();

            if (empty($questions)) {
                return [
                    'status' => 'NOK',
                    'message' => 'No questions with AI explanations found for the specified criteria'
                ];
            }

            // Get a random question
            $randomQuestion = $questions[array_rand($questions)];

            return [
                'status' => 'OK',
                'question' => [
                    'id' => $randomQuestion->getId(),
                    'question' => $randomQuestion->getQuestion(),
                    'ai_explanation' => $randomQuestion->getAiExplanation(),
                    'subject' => [
                        'id' => $randomQuestion->getSubject()->getId(),
                        'name' => $randomQuestion->getSubject()->getName()
                    ]
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting random question: ' . $e->getMessage()
            ];
        }
    }

    public function deleteTestLearners(): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Find all test learners
            $qb = $this->em->createQueryBuilder();
            $qb->select('l')
                ->from(Learner::class, 'l')
                ->where('l.name LIKE :testName OR l.email LIKE :testEmail')
                ->setParameter('testName', '%test%')
                ->setParameter('testEmail', '%test%');

            $testLearners = $qb->getQuery()->getResult();

            if (empty($testLearners)) {
                return [
                    'status' => 'OK',
                    'message' => 'No test learners found',
                    'deleted_count' => 0
                ];
            }

            $deletedCount = 0;

            // Begin transaction
            $this->em->beginTransaction();
            try {
                foreach ($testLearners as $learner) {
                    // Delete associated results
                    $results = $this->em->getRepository(Result::class)->findBy(['learner' => $learner]);
                    foreach ($results as $result) {
                        $this->em->remove($result);
                    }

                    // Delete the learner
                    $this->em->remove($learner);
                    $deletedCount++;
                }

                $this->em->flush();
                $this->em->commit();

                return [
                    'status' => 'OK',
                    'message' => 'Successfully deleted test learners and their data',
                    'deleted_count' => $deletedCount
                ];

            } catch (\Exception $e) {
                $this->em->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error deleting test learners: ' . $e->getMessage()
            ];
        }
    }

    public function createReportedMessage(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!isset($data['author_id']) || !isset($data['reporter_id']) || !isset($data['message_uid']) || !isset($data['message'])) {
                return [
                    'status' => 'NOK',
                    'message' => 'Missing required fields: author_id, reporter_id, message_uid, and message are required'
                ];
            }

            // Get the author and reporter learners
            $author = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $data['author_id']]);
            $reporter = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $data['reporter_id']]);

            if (!$author || !$reporter) {
                return [
                    'status' => 'NOK',
                    'message' => 'Author or reporter not found'
                ];
            }

            // Create new reported message
            $reportedMessage = new ReportedMessages();
            $reportedMessage->setAuthor($author);
            $reportedMessage->setReporter($reporter);
            $reportedMessage->setMessageUid($data['message_uid']);
            $reportedMessage->setMessage($data['message']);

            // Persist and flush
            $this->em->persist($reportedMessage);
            $this->em->flush();

            return [
                'status' => 'OK',
                'message' => 'Report created successfully',
                'report_id' => $reportedMessage->getId()
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error creating report: ' . $e->getMessage()
            ];
        }
    }

    public function getReportedMessages(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $authorId = $request->query->get('author_id');
            $reporterId = $request->query->get('reporter_id');
            $messageUid = $request->query->get('message_uid');
            $limit = $request->query->get('limit', 50);
            $offset = $request->query->get('offset', 0);

            $qb = $this->em->createQueryBuilder();
            $qb->select('r')
                ->from(ReportedMessages::class, 'r')
                ->orderBy('r.createdAt', 'DESC');

            // Add filters if provided
            if ($authorId) {
                $qb->andWhere('r.author = :author')
                    ->setParameter('author', $authorId);
            }

            if ($reporterId) {
                $qb->andWhere('r.reporter = :reporter')
                    ->setParameter('reporter', $reporterId);
            }

            if ($messageUid) {
                $qb->andWhere('r.messageUid = :messageUid')
                    ->setParameter('messageUid', $messageUid);
            }

            // Add pagination
            $qb->setFirstResult($offset)
                ->setMaxResults($limit);

            $reports = $qb->getQuery()->getResult();

            // Format the response
            $formattedReports = [];
            foreach ($reports as $report) {
                $formattedReports[] = [
                    'id' => $report->getId(),
                    'created_at' => $report->getCreatedAt()->format('Y-m-d H:i:s'),
                    'author_id' => $report->getAuthor()->getUid(),
                    'author_name' => $report->getAuthor()->getName(),
                    'reporter_id' => $report->getReporter()->getUid(),
                    'reporter_name' => $report->getReporter()->getName(),
                    'message_uid' => $report->getMessageUid(),
                    'message' => $report->getMessage()
                ];
            }

            return [
                'status' => 'OK',
                'message' => 'Reports retrieved successfully',
                'reports' => $formattedReports,
                'total' => count($reports),
                'limit' => $limit,
                'offset' => $offset
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving reports: ' . $e->getMessage()
            ];
        }
    }

    public function removeRecordedQuestionsBySubject(string $subjectName, int $grade): array
    {
        try {
            $this->logger->info("Starting Method: " . __METHOD__);

            $subject = $this->em->getRepository(Subject::class)->findOneBy(['name' => $subjectName, 'grade' => $grade]);
            if (!$subject) {
                return array(
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                );
            }

            $qb = $this->em->createQueryBuilder();
            $qb->delete('App\Entity\RecordedQuestion', 'rq')
                ->where('rq.subjectId = :subjectId')
                ->setParameter('subjectId', $subject->getId());

            $result = $qb->getQuery()->execute();

            return array(
                'status' => 'OK',
                'message' => 'Recorded questions removed successfully',
                'count' => $result
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error removing recorded questions'
            );
        }
    }

    public function getMessages(Request $request): array
    {
        try {
            $messages = $this->em->getRepository(Message::class)->findAllOrderedByDate();

            return [
                'success' => true,
                'data' => array_map(function (Message $message) {
                    return [
                        'id' => $message->getId(),
                        'title' => $message->getTitle(),
                        'message' => $message->getMessage(),
                        'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s')
                    ];
                }, $messages)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting messages: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve messages'
            ];
        }
    }

    public function updateLearnerVersion(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        $uid = $data['uid'] ?? null;
        $version = $data['version'] ?? null;
        $os = $data['os'] ?? null;

        $this->logger->info("uid: " . $uid);
        $this->logger->info("version: " . $version);
        $this->logger->info("os: " . $os);

        if (!$uid || !$version || !$os) {
            return [
                'success' => false,
                'message' => 'Missing required parameters: uid, version, and os are required'
            ];
        }

        $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return [
                'success' => false,
                'message' => 'Learner not found'
            ];
        }

        try {
            $learner->setVersion($version);
            $learner->setOs($os);
            $this->em->persist($learner);
            $this->em->flush();

            return [
                'success' => true,
                'message' => 'Learner version and OS updated successfully'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error updating learner version and OS: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating learner version and OS'
            ];
        }
    }

    public function getQuestionsWithSameContext(int $questionId, ?string $topic = null): array
    {
        try {
            // Get the question to find its context and subject
            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            $context = $question->getContext();
            $subject = $question->getSubject();
            $year = $question->getYear();
            $term = $question->getTerm();

            // Find questions with the same context or image path and same subject
            $qb = $this->em->createQueryBuilder();
            $qb->select('q.id')
                ->from('App\Entity\Question', 'q')
                ->where('q.subject = :subject')
                ->andWhere('q.active = :active')
                ->andWhere('q.context IS NOT NULL')
                ->andWhere('q.context = :context')
                ->andWhere('q.year = :year')
                ->andWhere('q.term = :term')
                ->setParameter('subject', $subject)
                ->setParameter('active', true)
                ->setParameter('context', $context)
                ->setParameter('year', $year)
                ->setParameter('term', $term);

            // If topic is provided, add it to the query

            if ($topic) {
                // Join with Topic entity to filter by main topic
                $qb->leftJoin('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic AND t.subject = s')
                    ->andWhere('t.name = :mainTopic')
                    ->setParameter('mainTopic', $topic);
            }


            $qb->orderBy('q.id', 'ASC');

            $results = $qb->getQuery()->getResult();
            $questionIds = array_map(function ($result) {
                return $result['id'];
            }, $results);

            return [
                'status' => 'OK',
                'message' => 'Successfully retrieved questions with same context or image path',
                'question_ids' => $questionIds
            ];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error retrieving questions with same context or image path'
            ];
        }
    }

    public function updateLearnerAvatar(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true);
            $uid = $data['uid'] ?? null;
            $avatar = $data['avatar'] ?? null;

            if (!$uid || !$avatar) {
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

            $learner->setAvatar($avatar);
            $this->em->persist($learner);
            $this->em->flush();

            return [
                'status' => 'OK',
                'message' => 'Avatar updated successfully',
                'avatar' => $avatar
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error updating learner avatar: ' . $e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error updating avatar'
            ];
        }
    }

    public function setOtherContextImages(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'] ?? null;
            $otherContextImages = $requestBody['other_context_images'] ?? null;

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

            $question->setOtherContextImages($otherContextImages);
            $this->em->persist($question);
            $this->em->flush();

            return array(
                'status' => 'OK',
                'message' => 'Successfully updated other context images for question'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return array(
                'status' => 'NOK',
                'message' => 'Error updating other context images for question'
            );
        }
    }

    public function updateLearnerNewThreadNotification(Request $request): array
    {
        $uid = $request->get('uid');
        $newThreadNotification = $request->get('newThreadNotification');

        if (!$uid) {
            return ['success' => false, 'message' => 'UID is required'];
        }

        if (!isset($newThreadNotification)) {
            return ['success' => false, 'message' => 'newThreadNotification is required'];
        }

        $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return ['success' => false, 'message' => 'Learner not found'];
        }

        $learner->setNewThreadNotification((bool) $newThreadNotification);
        $this->em->persist($learner);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Notification setting updated successfully',
            'newThreadNotification' => $learner->getNewThreadNotification()
        ];
    }

    public function updateQuestionTopic(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            // Validate admin access
            $adminCheck = $this->validateAdminAccess($request);
            if ($adminCheck['status'] === 'NOK') {
                return $adminCheck;
            }

            $requestBody = json_decode($request->getContent(), true);
            $questionId = $requestBody['question_id'] ?? null;
            $topic = $requestBody['topic'] ?? null;

            if (empty($questionId)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question ID is required'
                ];
            }

            $question = $this->em->getRepository(Question::class)->find($questionId);
            if (!$question) {
                return [
                    'status' => 'NOK',
                    'message' => 'Question not found'
                ];
            }

            $question->setTopic($topic);
            $question->setUpdated(new \DateTime());
            $this->em->flush();

            return [
                'status' => 'OK',
                'message' => 'Successfully updated question topic',
                'question_id' => $question->getId()
            ];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error updating question topic'
            ];
        }
    }

    public function getAndMarkFirstUnpostedQuestion(): ?Question
    {
        $question = $this->em->getRepository(Question::class)
            ->createQueryBuilder('q')
            ->where('q.posted = :posted')
            ->andWhere('q.aiExplanation IS NOT NULL')
            ->andWhere('q.aiExplanation != :empty')
            ->setParameter('posted', false)
            ->setParameter('empty', '')
            ->orderBy('q.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($question) {
            $question->setPosted(true);
            $this->em->persist($question);
            $this->em->flush();
        }

        return $question;
    }

    public function getUniqueTopicsForSubject(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $this->logger->info("Request: test 1");
            $subjectName = $request->query->get('subject_name');
            $uid = $request->query->get('uid');

            if (empty($subjectName) || empty($uid)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Subject name and user ID are required'
                ];
            }

            // Get the learner
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            $this->logger->info("Learner: " . json_encode($learner));


            // Get learner's terms and curriculum
            $learnerTerms = $learner->getTerms() ? array_map(function ($term) {
                return trim(str_replace('"', '', $term));
            }, explode(',', $learner->getTerms())) : [];

            $learnerCurriculum = $learner->getCurriculum() ? array_map(function ($curr) {
                return trim(str_replace('"', '', $curr));
            }, explode(',', $learner->getCurriculum())) : [];

            // Get subjects by name and grade
            $subjects = $this->em->getRepository(Subject::class)
                ->createQueryBuilder('s')
                ->where('s.name LIKE :subjectName')
                ->andWhere('s.grade = :grade')
                ->setParameter('subjectName', '%' . $subjectName . '%')
                ->setParameter('grade', $learner->getGrade())
                ->getQuery()
                ->getResult();

            if (empty($subjects)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                ];
            }



            $subjectIds = array_map(function ($subject) {
                return $subject->getId();
            }, $subjects);

            $this->logger->info("Subjects: " . json_encode($subjectIds));

            // Get unique topics with their main topics and question counts
            $qb = $this->em->getRepository(Question::class)
                ->createQueryBuilder('q')
                ->select('DISTINCT q.topic, t.name as mainTopic, COUNT(q.id) as questionCount')
                ->leftJoin('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic AND t.subject IN (:subjects)')
                ->where('q.subject IN (:subjects)')
                ->andWhere('q.active = :active')
                ->andWhere('q.status = :status')
                ->andWhere('q.topic IS NOT NULL')
                ->setParameter('subjects', $subjectIds)
                ->setParameter('active', true)
                ->setParameter('status', 'approved')
                ->groupBy('q.topic, t.name');

            // Add term filter if learner has terms specified
            if (!empty($learnerTerms)) {
                $qb->andWhere($qb->expr()->in('q.term', ':terms'))
                    ->setParameter('terms', $learnerTerms);
            }

            // Add curriculum filter if learner has curriculum specified
            if (!empty($learnerCurriculum)) {
                $qb->andWhere($qb->expr()->in('q.curriculum', ':curriculum'))
                    ->setParameter('curriculum', $learnerCurriculum);
            }

            $topics = $qb->orderBy('t.name', 'ASC')
                ->addOrderBy('q.topic', 'ASC')
                ->getQuery()
                ->getResult();

            // Group topics by main topic
            $groupedTopics = [];
            foreach ($topics as $topic) {
                $mainTopic = $topic['mainTopic'] ?? 'Uncategorized';
                if (!isset($groupedTopics[$mainTopic])) {
                    $groupedTopics[$mainTopic] = [];
                }
                if (!empty($topic['topic'])) {
                    $groupedTopics[$mainTopic][] = [
                        'name' => $topic['topic'],
                        'questionCount' => (int) $topic['questionCount']
                    ];
                }
            }

            // Sort topics within each main topic by name
            foreach ($groupedTopics as &$subtopics) {
                usort($subtopics, function ($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            }

            return [
                'status' => 'OK',
                'topics' => $groupedTopics,
                'subjects' => array_map(function ($subject) {
                    return [
                        'id' => $subject->getId(),
                        'name' => $subject->getName()
                    ];
                }, $subjects)
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting topics'
            ];
        }
    }

    public function getTopicProgress(Request $request): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $uid = $request->query->get('uid');
            $topic = $request->query->get('topic');
            $subjectName = $request->query->get('subject_name');

            if (empty($uid) || empty($topic) || empty($subjectName)) {
                return [
                    'status' => 'NOK',
                    'message' => 'User ID, topic, and subject name are required'
                ];
            }

            // Get the learner
            $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Get subjects by name and grade
            $subjects = $this->em->getRepository(Subject::class)
                ->createQueryBuilder('s')
                ->where('s.name LIKE :subjectName')
                ->andWhere('s.grade = :grade')
                ->setParameter('subjectName', '%' . $subjectName . '%')
                ->setParameter('grade', $learner->getGrade())
                ->getQuery()
                ->getResult();

            if (empty($subjects)) {
                return [
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                ];
            }

            $subjectIds = array_map(function ($subject) {
                return $subject->getId();
            }, $subjects);

            // Get total questions for this topic and subject
            $qb = $this->em->createQueryBuilder();
            $qb->select('COUNT(q.id)')
                ->from('App\Entity\Question', 'q')
                ->join('q.subject', 's')
                ->join('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic')
                ->where('t.name = :topic')
                ->andWhere('q.subject IN (:subjects)')
                ->andWhere('q.active = true')
                ->andWhere('q.status = :status')
                ->setParameter('topic', $topic)
                ->setParameter('subjects', $subjectIds)
                ->setParameter('status', 'approved');

            $totalQuestions = $qb->getQuery()->getSingleScalarResult();

            // Get viewed questions from topic tracker
            $topicLessonsTracker = $learner->getTopicLessonsTracker() ?? [];
            $viewedQuestions = $topicLessonsTracker[$topic] ?? [];
            $viewedCount = count($viewedQuestions);

            $progressPercentage = $totalQuestions > 0 ? round(($viewedCount / $totalQuestions) * 100) : 0;

            return [
                'status' => 'OK',
                'total_questions' => (int) $totalQuestions,
                'viewed_questions' => $viewedCount,
                'progress_percentage' => $progressPercentage
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting topic progress ' . $e->getMessage()
            ];
        }
    }

}
