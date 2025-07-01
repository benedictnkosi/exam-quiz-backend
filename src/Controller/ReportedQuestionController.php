<?php

namespace App\Controller;

use App\Entity\ReportedQuestion;
use App\Entity\Subject;
use App\Repository\ReportedQuestionRepository;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reported-questions')]
class ReportedQuestionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReportedQuestionRepository $reportedQuestionRepository,
        private readonly SubjectRepository $subjectRepository
    ) {
    }

    /**
     * Create a new reported question
     * 
     * Request body should contain:
     * {
     *     "subject_id": 1,           // Required: Subject ID
     *     "question_text": "text",   // Required: Question text
     *     "question_topic": "topic", // Optional: Question topic
     *     "sub_topic": "subtopic"    // Optional: Sub topic
     * }
     */
    #[Route('', name: 'create_reported_question', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['subject_id'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Subject ID is required'
            ], 400);
        }

        if (!isset($data['question_text'])) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Question text is required'
            ], 400);
        }

        // Find the subject
        $subject = $this->subjectRepository->find($data['subject_id']);
        if (!$subject) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Subject not found'
            ], 404);
        }

        // Create new reported question
        $reportedQuestion = new ReportedQuestion();
        $reportedQuestion->setSubject($subject);
        $reportedQuestion->setQuestionText($data['question_text']);
        $reportedQuestion->setQuestionTopic($data['question_topic'] ?? null);
        $reportedQuestion->setSubTopic($data['sub_topic'] ?? null);

        $this->reportedQuestionRepository->save($reportedQuestion, true);

        return $this->json([
            'status' => 'OK',
            'message' => 'Reported question created successfully',
            'data' => [
                'id' => $reportedQuestion->getId(),
                'subject' => $subject->getName(),
                'question_text' => $reportedQuestion->getQuestionText(),
                'question_topic' => $reportedQuestion->getQuestionTopic(),
                'sub_topic' => $reportedQuestion->getSubTopic(),
                'created_at' => $reportedQuestion->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        ], 201);
    }

    /**
     * Get all reported questions
     */
    #[Route('', name: 'get_all_reported_questions', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $reportedQuestions = $this->reportedQuestionRepository->findAll();

        $data = [];
        foreach ($reportedQuestions as $reportedQuestion) {
            $data[] = [
                'id' => $reportedQuestion->getId(),
                'subject' => $reportedQuestion->getSubject() ? $reportedQuestion->getSubject()->getName() : null,
                'subject_id' => $reportedQuestion->getSubject() ? $reportedQuestion->getSubject()->getId() : null,
                'question_text' => $reportedQuestion->getQuestionText(),
                'question_topic' => $reportedQuestion->getQuestionTopic(),
                'sub_topic' => $reportedQuestion->getSubTopic(),
                'created_at' => $reportedQuestion->getCreatedAt() ? $reportedQuestion->getCreatedAt()->format('Y-m-d H:i:s') : null,
                'updated_at' => $reportedQuestion->getUpdatedAt() ? $reportedQuestion->getUpdatedAt()->format('Y-m-d H:i:s') : null
            ];
        }

        return $this->json([
            'status' => 'OK',
            'data' => $data,
            'count' => count($data)
        ]);
    }

    /**
     * Delete a reported question
     */
    #[Route('/{id}', name: 'delete_reported_question', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $reportedQuestion = $this->reportedQuestionRepository->find($id);

        if (!$reportedQuestion) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Reported question not found'
            ], 404);
        }

        $this->reportedQuestionRepository->remove($reportedQuestion, true);

        return $this->json([
            'status' => 'OK',
            'message' => 'Reported question deleted successfully'
        ]);
    }
} 