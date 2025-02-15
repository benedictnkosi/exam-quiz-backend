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

    #[Route('/learn/learner/create', name: 'create_learner', methods: ['POST'])]
    public function createLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->createLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
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

    #[Route('/learn/question/random', name: 'get_random_question', methods: ['GET'])]
    public function getRandomQuestionBySubjectId(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $subjectId = $request->query->get('subject_id');
        $uid = $request->query->get('uid');
        $questionId = $request->query->get('question_id') ?? 0;
        $showAllQuestions = $request->query->get('show_all_questions') ?? 'yes';
        $this->logger->info("showAllQuestions: " . $showAllQuestions);
        $response = $this->api->getRandomQuestionBySubjectId($subjectId, $uid, $questionId, $showAllQuestions);
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

    #[Route('/learn/learner/update', name: 'update_learner', methods: ['POST'])]
    public function updateLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->updateLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/assign-subject', name: 'assign_subject', methods: ['POST'])]
    public function assignSubjectToLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->assignSubjectToLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/subjects-not-enrolled', name: 'get_subjects_not_enrolled', methods: ['GET'])]
    public function getSubjectsNotEnrolledByLearner(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getSubjectsNotEnrolledByLearner($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);

        if (isset($response['status']) && $response['status'] === 'NOK') {
            return new JsonResponse($jsonContent, 404, array('Access-Control-Allow-Origin' => '*'), true);
        }

        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/check-answer', name: 'check_answer', methods: ['POST'])]
    public function checkLearnerAnswer(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->checkLearnerAnswer($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/learner/remove-results', name: 'remove_results', methods: ['POST'])]
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

    #[Route('/learn/learner/update-overide-term', name: 'update_override_term', methods: ['POST'])]
    public function updateOverideTerm(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->setOverrideTerm($request);
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
        $uploadDir = __DIR__ . '/../../public/assets/images/learnMzansi/';
        return new BinaryFileResponse($uploadDir . $request->query->get('image'));
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

    #[Route('/learn/issue/log', name: 'log_issue', methods: ['POST'])]
    public function logIssue(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->logIssue($request);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($response, 'json', $context);
        return new JsonResponse($jsonContent, 200, array('Access-Control-Allow-Origin' => '*'), true);
    }

    #[Route('/learn/issues/active', name: 'get_active_issues', methods: ['GET'])]
    public function getAllActiveIssues(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getAllActiveIssues($request);
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

    #[Route('/learn/questions/captured-per-week', name: 'get_questions_per_week', methods: ['GET'])]
    public function getQuestionsCapturedPerWeek(Request $request): JsonResponse
    {
        $this->logger->info("Starting Method: " . __METHOD__);
        $response = $this->api->getQuestionsCapturedPerWeek($request);
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
}
