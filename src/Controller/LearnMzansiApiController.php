<?php

namespace App\Controller;

use App\Service\LearnMzansiApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializationContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Service\LearnerGradeStatsService;
use App\Service\LearnerSubjectStatsService;
use App\Service\EarlyAccessService;
use App\Service\ReviewedQuestionsService;
use App\Service\FavoriteQuestionService;
use App\Service\CheckAnswerService;
use App\Service\SchoolFactService;
use App\Service\QuestionStatsService;
use App\Service\TestDataCleanupService;
use App\Service\SmallestImageService;

#[Route('/public', name: 'api_')]
class LearnMzansiApiController extends AbstractController
{
    private $serializer;

    public function __construct(
        private LearnMzansiApi $api,
        private LoggerInterface $logger
    ) {
        $this->serializer = SerializerBuilder::create()->build();
    }


    #[Route('/learn/learner', name: 'get_learner', methods: ['GET'])]
    public function getLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/create', name: 'create_learner', methods: ['POST'])]
    public function createLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->createLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }


    #[Route('/learn/grades', name: 'grades', methods: ['GET'])]
    public function getGrades(): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getGrades();
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/questions', name: 'get_question_by_id', methods: ['GET'])]
    public function getQuestionById(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getQuestionById($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/question/create', name: 'create_question', methods: ['POST'])]
    public function createQuestion(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $data = json_decode($request->getContent(), true);
        $response = $this->api->createQuestion($data, $request);
        $this->logger->info("Response: " . json_encode($response));
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }


    #[Route('/learn/question/byname', name: 'get_random_question_by_name', methods: ['GET'])]
    public function getRandomQuestionBySubjectName(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $subjectName = $request->query->get('subject_name');
        $paperName = $request->query->get('paper_name');
        $uid = $request->query->get('uid');
        $questionId = $request->query->get('question_id') ?? 0;
        $response = $this->api->getRandomQuestionBySubjectName($subjectName, $paperName, $uid, $questionId);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/subjects', name: 'get_learner_subjects', methods: ['GET'])]
    public function getLearnerSubjects(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getLearnerSubjects($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);

        if (isset($response['status']) && $response['status'] === 'NOK') {
            return new JsonResponse($jsonContent, 404, array('Access-Control-Allow-Origin' => '*'), true);
        }

        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }


    #[Route('/learn/learner/check-answer', name: 'check_answer', methods: ['POST'])]
    public function checkLearnerAnswer(
        Request $request,
        CheckAnswerService $checkAnswerService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $data = json_decode($request->getContent(), true);
        $uid = $data['uid'] ?? null;
        $questionId = $data['question_id'] ?? null;
        $answer = $data['answer'] ?? null;
        $duration = $data['duration'] ?? null;

        if (!$uid || !$questionId || $answer === null) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Missing required fields: uid, question_id, and answer are required'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $response = $checkAnswerService->checkAnswer($uid, (int) $questionId, $answer, $duration);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/learner/remove-results', name: 'remove_results', methods: ['DELETE'])]
    public function removeLearnerResultsBySubject(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->removeLearnerResultsBySubject($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/subject-percentage', name: 'get_subject_percentage', methods: ['GET'])]
    public function getLearnerSubjectPercentage(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getLearnerSubjectPercentage($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }



    #[Route('/learn/learner/set-higher-grade-flag', name: 'set_higher_grade', methods: ['POST'])]
    public function setHigherGradeFlag(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->setHigherGradeFlag($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/subjects/active', name: 'get_active_subjects', methods: ['GET'])]
    public function getAllActiveSubjects(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getAllActiveSubjects($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/upload-image', name: 'upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->uploadImage($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/get-image', name: 'get_image', methods: ['GET'])]
    public function getImage(Request $request): Response
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $imageName = $request->query->get('image');
        if (!$imageName) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Image parameter is required'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $uploadDir = __DIR__ . '/../../public/assets/images/learnMzansi/';
        $imagePath = $uploadDir . $imageName;

        if (!file_exists($imagePath)) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Image not found'
            ], Response::HTTP_NOT_FOUND, ['Access-Control-Allow-Origin' => '*']);
        }

        try {
            return new BinaryFileResponse($imagePath, Response::HTTP_OK, [
                'Access-Control-Allow-Origin' => '*',
                'Content-Type' => mime_content_type($imagePath)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in getImage: ' . $e->getMessage());
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Error retrieving image'
            ], Response::HTTP_INTERNAL_SERVER_ERROR, ['Access-Control-Allow-Origin' => '*']);
        }
    }

    #[Route('/learn/question/set-image-path', name: 'set_image_path', methods: ['POST'])]
    public function setImagePathForQuestion(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->setImagePathForQuestion($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/question/set-image-for-answer', name: 'set_answer_image', methods: ['POST'])]
    public function setImageForQuestionAnswer(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->setImageForQuestionAnswer($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/questions/by-grade-subject', name: 'get_questions_by_grade', methods: ['GET'])]
    public function getQuestionsByGradeAndSubject(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getQuestionsByGradeAndSubject($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/question/set-inactive', name: 'set_question_inactive', methods: ['POST'])]
    public function setQuestionInactive(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->setQuestionInactive($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/remove-subject', name: 'remove_subject', methods: ['POST'])]
    public function removeSubjectFromLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->removeSubjectFromLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/question/set-status', name: 'set_question_status', methods: ['POST'])]
    public function setQuestionStatus(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->setQuestionStatus($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/subject/create', name: 'create_subject', methods: ['POST'])]
    public function createSubject(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->createSubject($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/subject/update-active', name: 'update_subject_active', methods: ['POST'])]
    public function updateSubjectActive(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->updateSubjectActive($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/subjects/by-grade', name: 'get_subjects_by_grade', methods: ['GET'])]
    public function getSubjectsByGrade(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getSubjectsByGrade($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/subjects/names', name: 'get_subject_names', methods: ['GET'])]
    public function getDistinctSubjectNames(): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getDistinctSubjectNames();
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/questions/captured', name: 'get_questions_captured', methods: ['GET'])]
    public function getQuestionsCaptured(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getQuestionsCaptured($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/questions/top-incorrect', name: 'get_top_incorrect', methods: ['GET'])]
    public function getTopIncorrectQuestions(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getTopIncorrectQuestions($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learners/created-per-month', name: 'get_learners_per_month', methods: ['GET'])]
    public function getLearnersCreatedPerMonth(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getLearnersCreatedPerMonth($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/questions/new-count', name: 'get_new_questions_count', methods: ['GET'])]
    public function getNewQuestionsCount(): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getNewQuestionsCount();
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/subjects/{gradeId}', name: 'subjects', methods: ['GET'])]
    public function getSubjects(int $gradeId): JsonResponse
    {
        $subjects = $this->api->getSubjects($gradeId);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($subjects, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/question/next-new', name: 'get_next_new_question', methods: ['GET'])]
    public function getNextNewQuestion(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $questionId = $request->query->get('question_id');
        if (!$questionId) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Question ID is required'
            ], 400);
        }

        $response = $this->api->getNextNewQuestion((int) $questionId);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, ['Access-Control-Allow-Origin' => '*'], true);
    }

    #[Route('/learn/questions/rejected', name: 'get_rejected_questions', methods: ['GET'])]
    public function getRejectedQuestions(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $capturer = $request->query->get('capturer');
        if (!$capturer) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Capturer parameter is required'
            ], 400);
        }

        $response = $this->api->getRejectedQuestionsByCapturer($capturer);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, ['Access-Control-Allow-Origin' => '*'], true);
    }

    #[Route('/learn/git/pull', name: 'git_pull', methods: ['GET'])]
    public function gitPull(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        try {
            $command = 'git config --global user.email nkosi.benedict@gmail.com';
            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );

            $command = 'git config --global user.name nkosibenedict';
            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );


            $command = 'git stash';
            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );

            $command = 'git pull https://github.com/benedictnkosi/exam-quiz-backend.git main --force';

            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );
            return new JsonResponse($responseArray, 200, array());
        } catch (Exception $ex) {
            $this->logger->error($ex->getMessage() . ' - ' . __METHOD__ . ':' . $ex->getLine() . ' ' . $ex->getTraceAsString());
        }
        return new JsonResponse($responseArray, 200, array());
    }

    #[Route('/learn/learner/subject-stats', name: 'get_subject_stats', methods: ['GET'])]
    public function getLearnerSubjectStats(
        Request $request,
        LearnerSubjectStatsService $subjectStatsService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $uid = $request->query->get('uid');
        $subjectName = $request->query->get('subject_name');

        if (!$uid || !$subjectName) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'UID and subject_name are required'
            ], 400);
        }

        $response = $subjectStatsService->getSubjectStats($uid, $subjectName);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, ['Access-Control-Allow-Origin' => '*'], true);
    }

    /**
     * Executes a command and reurns an array with exit code, stdout and stderr content
     * @param string $cmd - Command to execute
     * @param string|null $workdir - Default working directory
     * @return string[] - Array with keys: 'code' - exit code, 'out' - stdout, 'err' - stderr
     */
    function execute($cmd, $workdir = null)
    {

        if (is_null($workdir)) {
            $workdir = __DIR__;
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w"),  // stderr
        );

        $process = proc_open($cmd, $descriptorspec, $pipes, $workdir, null);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'code' => proc_close($process),
            'out' => trim($stdout),
            'err' => trim($stderr),
        ];
    }

    #[Route('/learn/subscribe', name: 'subscribe', methods: ['GET'])]
    public function subscribe(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->subscribe($request);
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/update-posted-status', name: 'update_question_posted_status', methods: ['POST'])]
    public function updateQuestionPostedStatus(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $response = $this->api->updateQuestionPostedStatus($request);
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/auto-reject', name: 'auto_reject_questions', methods: ['POST'])]
    public function autoRejectQuestions(): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->autoRejectQuestions();
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/convert-image-to-text', name: 'convert_image_to_text', methods: ['GET'])]
    public function convertImageToText(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->convertImagesToText($request);
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/ai-explanation', name: 'get_ai_explanation', methods: ['GET'])]
    public function getAIExplanation(
        Request $request,
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $questionId = $request->query->get('question_id');
        if (!$questionId) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Question ID is required'
            ], 400);
        }

        $response = $this->api->getAIExplanation((int) $questionId);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, ['Access-Control-Allow-Origin' => '*'], true);
    }

    #[Route('/learn/school/fact', name: 'get_school_fact', methods: ['GET'])]
    public function getSchoolFact(
        Request $request
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $response = $this->api->getSchoolFact($request);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/learner/delete', name: 'delete_learner', methods: ['POST', 'DELETE'])]
    public function deleteLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->deleteLearner($request);
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question-status-counts', name: 'question_status_counts', methods: ['GET'])]
    public function getQuestionStatusCounts(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getQuestionStatusCountsByCapturers($request);
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/questions-reviewed', name: 'questions_reviewed', methods: ['GET'])]
    public function getQuestionsReviewed(
        Request $request,
        ReviewedQuestionsService $reviewedQuestionsService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $fromDate = $request->query->get('from_date');
        $response = $reviewedQuestionsService->getReviewerStats($fromDate);

        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;
        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/early-access', name: 'early_access_register', methods: ['GET', 'OPTIONS'])]
    public function registerEarlyAccess(
        Request $request,
        EarlyAccessService $earlyAccessService
    ): JsonResponse {
        // Handle OPTIONS request for CORS
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type'
            ]);
        }

        $this->logger->info("Starting Method: " . __METHOD__);

        $email = $request->query->get('email');

        if (!$email) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Email is required'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $response = $earlyAccessService->registerEmail($email);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse(
            $response,
            $statusCode,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    #[Route('/learn/question/favorite', name: 'get_favorites', methods: ['GET'])]
    public function getFavorites(
        Request $request,
        FavoriteQuestionService $favoriteService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $uid = $request->query->get('uid');
        $subjectName = $request->query->get('subject_name');

        if (!$uid || !$subjectName) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Learner ID and Subject Name are required'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $response = $favoriteService->getFavorites($uid, $subjectName);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/favorite', name: 'remove_favorite', methods: ['DELETE'])]
    public function removeFavorite(
        Request $request,
        FavoriteQuestionService $favoriteService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $data = json_decode($request->getContent(), true);
        $questionId = $data['question_id'] ?? null;
        $uid = $data['uid'] ?? null;

        if (!$questionId || !$uid) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Question ID and Learner ID are required'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $response = $favoriteService->removeFavorite((int) $questionId, $uid);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/favorite', name: 'add_favorite', methods: ['POST'])]
    public function addFavorite(
        Request $request,
        FavoriteQuestionService $favoriteService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $data = json_decode($request->getContent(), true);
        $questionId = $data['question_id'] ?? null;
        $uid = $data['uid'] ?? null;

        if (!$questionId || !$uid) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'Question ID and Learner ID are required'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $response = $favoriteService->addFavorite((int) $questionId, $uid);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/stats/questions', name: 'get_question_stats', methods: ['GET'])]
    public function getQuestionStats(
        Request $request,
        QuestionStatsService $questionStatsService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $fromDate = $request->query->get('fromDate');
        $endDate = $request->query->get('endDate');
        if (!$fromDate || !$endDate) {
            return new JsonResponse([
                'status' => 'NOK',
                'message' => 'fromDate and endDate parameters are required (YYYY-MM-DD format)'
            ], Response::HTTP_BAD_REQUEST, ['Access-Control-Allow-Origin' => '*']);
        }

        $response = $questionStatsService->getQuestionStats($fromDate, $endDate);
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/cleanup/test-data', name: 'cleanup_test_data', methods: ['DELETE'])]
    public function cleanupTestData(
        TestDataCleanupService $cleanupService
    ): JsonResponse {
        $this->logger->info("Starting Method: " . __METHOD__);

        $response = $cleanupService->cleanupTestData();
        $statusCode = $response['status'] === 'OK' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return new JsonResponse($response, $statusCode, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/delete', name: 'delete_question', methods: ['DELETE'])]
    public function deleteQuestion(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->deleteQuestion($request);
        return new JsonResponse($response, 200, ['Access-Control-Allow-Origin' => '*']);
    }



    #[Route('/learn/smallest-images', name: 'get_smallest_images', methods: ['GET'])]
    public function getSmallestImages(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        $response = $this->api->getSmallestImages($request);

        return new JsonResponse($response, Response::HTTP_OK, ['Access-Control-Allow-Origin' => '*']);
    }

    #[Route('/learn/question/random', name: 'get_random_question', methods: ['GET'])]
    public function getRandomQuestionWithRevision(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getRandomQuestionWithRevision($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/question/random-ai', name: 'get_random_question_ai', methods: ['GET'])]
    public function getRandomQuestionWithAIExplanation(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getRandomQuestionWithAIExplanation($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }
}
