<?php

namespace App\Controller;

use App\Entity\ExamPaper;
use App\Service\ExamPaperUploadService;
use App\Service\ExamPaperStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/api/exam-papers')]
class ExamPaperController extends AbstractController
{
    public function __construct(
        private ExamPaperUploadService $uploadService,
        private ExamPaperStatusService $statusService,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/upload', name: 'app_exam_paper_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('file');
            if (!$file) {
                return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
            }

            $type = $request->request->get('type');
            if (!in_array($type, ['paper', 'memo'])) {
                return $this->json(['error' => 'Invalid type. Must be either "paper" or "memo"'], Response::HTTP_BAD_REQUEST);
            }

            $subjectName = $request->request->get('subjectName');
            $grade = (int) $request->request->get('grade');
            $year = (int) $request->request->get('year');
            $term = $request->request->get('term');
            $userUid = $request->request->get('userUid');

            if (!$userUid) {
                return $this->json(['error' => 'User UID is required'], Response::HTTP_BAD_REQUEST);
            }

            // For memo uploads, require examPaperId
            if ($type === 'memo') {
                $examPaperId = $request->request->get('examPaperId');
                if (!$examPaperId) {
                    return $this->json(['error' => 'examPaperId is required for memo uploads'], Response::HTTP_BAD_REQUEST);
                }
            }

            $examPaper = $this->uploadService->uploadPaper(
                $file,
                $type,
                $subjectName,
                $grade,
                $year,
                $term,
                $userUid,
                $type === 'memo' ? $examPaperId : null
            );

            $context = SerializationContext::create()->setGroups(['exam_paper:read']);
            $jsonContent = $this->serializer->serialize($examPaper, 'json', $context);

            return new JsonResponse([
                'message' => 'File uploaded successfully',
                'examPaper' => json_decode($jsonContent, true)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/upload-images', name: 'exam_paper_upload_images', methods: ['POST'])]
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        try {
            $this->logger->info('STARTING Uploading image for exam paper: ' . $id);

            // Get the question number from the request
            $questionNumber = $request->request->get('questionNumber');
            if (!$questionNumber) {
                return $this->json(['error' => 'Question number is required'], 400);
            }

            // Get the file
            $file = $request->files->get('image');
            if (!$file) {
                return $this->json(['error' => 'No image uploaded'], 400);
            }

            // Get the exam paper
            $this->logger->info('Getting exam paper: ' . $id);
            $examPaper = $this->uploadService->getExamPaperRepository()->find($id);
            if (!$examPaper) {
                return $this->json(['error' => 'Exam paper not found'], 404);
            }

            // Validate the file
            $this->logger->info('Validating file: ' . $file->getClientOriginalName());
            $violations = $this->validator->validate($file, [
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or GIF)',
                ])
            ]);

            if (count($violations) > 0) {
                $this->logger->error('Validation failed: ' . (string) $violations);
                return $this->json(['error' => (string) $violations], 400);
            }

            // Upload the image
            $this->logger->info('Uploading image for question: ' . $questionNumber);
            $imagePath = $this->uploadService->uploadSingleImage($file);

            // Get existing images or initialize empty array
            $images = $examPaper->getImages() ?? [];
            if (!is_array($images)) {
                // If images is not an array (old format), initialize as empty array
                $images = [];
            }

            // Add the new image
            $images[$questionNumber] = $imagePath;

            // Update the exam paper
            $examPaper->setImages($images);

            // Save the exam paper
            $this->uploadService->getExamPaperRepository()->save($examPaper, true);

            $context = SerializationContext::create()->setGroups(['exam_paper:read']);
            $jsonContent = $this->serializer->serialize($examPaper, 'json', $context);

            return new JsonResponse([
                'message' => 'Image uploaded successfully',
                'examPaper' => json_decode($jsonContent, true)
            ], 201);

        } catch (\Exception $e) {
            $this->logger->error('Error uploading image: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/question-image/{imageName}', name: 'get_question_image', methods: ['GET'])]
    public function getQuestionImage(string $imageName): Response
    {
        $filePath = $this->uploadService->getImageFilePath($imageName);
        if (!$filePath || !file_exists($filePath)) {
            return new JsonResponse(['error' => 'Image not found'], 404);
        }
        $mimeType = mime_content_type($filePath);
        return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_INLINE, ['Content-Type' => $mimeType]);
    }

    #[Route('/{id}/remove-images', name: 'exam_paper_remove_image', methods: ['DELETE'])]
    public function removeImage(Request $request, int $id): JsonResponse
    {
        try {
            $this->logger->info('Removing image for exam paper: ' . $id);

            // Get the question number from the query parameters
            $questionNumber = $request->query->get('questionNumber');
            if (!$questionNumber) {
                return $this->json(['error' => 'Question number is required'], 400);
            }

            // Get the exam paper
            $examPaper = $this->uploadService->getExamPaperRepository()->find($id);
            if (!$examPaper) {
                return $this->json(['error' => 'Exam paper not found'], 404);
            }

            // Remove the image
            $success = $this->uploadService->removeImage($examPaper, $questionNumber);
            if (!$success) {
                return $this->json(['error' => 'Image not found for this question'], 404);
            }

            $context = SerializationContext::create()->setGroups(['exam_paper:read']);
            $jsonContent = $this->serializer->serialize($examPaper, 'json', $context);

            return new JsonResponse([
                'message' => 'Image removed successfully',
                'examPaper' => json_decode($jsonContent, true)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error removing image: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/status', name: 'exam_paper_update_status', methods: ['PATCH'])]
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!isset($data['status'])) {
                return $this->json(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
            }

            $examPaper = $this->statusService->updateStatus($id, $data['status']);

            $context = SerializationContext::create()->setGroups(['exam_paper:read']);
            $jsonContent = $this->serializer->serialize($examPaper, 'json', $context);

            return new JsonResponse([
                'message' => 'Status updated successfully',
                'examPaper' => json_decode($jsonContent, true)
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Error updating exam paper status: ' . $e->getMessage());
            return $this->json(['error' => 'An error occurred while updating the status'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'exam_paper_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            // Get query parameters
            $filters = [];
            $orderBy = ['field' => 'id', 'direction' => 'DESC'];
            $limit = null;
            $offset = null;

            // Parse filters
            if ($request->query->has('status')) {
                $filters['status'] = $request->query->get('status');
            }
            if ($request->query->has('grade')) {
                $filters['grade'] = (int) $request->query->get('grade');
            }
            if ($request->query->has('year')) {
                $filters['year'] = (int) $request->query->get('year');
            }
            if ($request->query->has('term')) {
                $filters['term'] = $request->query->get('term');
            }
            if ($request->query->has('subjectName')) {
                $filters['subjectName'] = $request->query->get('subjectName');
            }

            // Parse ordering
            if ($request->query->has('orderBy')) {
                $orderBy['field'] = $request->query->get('orderBy');
            }
            if ($request->query->has('orderDirection')) {
                $orderBy['direction'] = strtoupper($request->query->get('orderDirection'));
            }

            // Parse pagination
            if ($request->query->has('limit')) {
                $limit = (int) $request->query->get('limit');
            }
            if ($request->query->has('offset')) {
                $offset = (int) $request->query->get('offset');
            }

            $examPapers = $this->statusService->getAllExamPapers($filters, $orderBy, $limit, $offset);

            $context = SerializationContext::create()->setGroups(['exam_paper:read']);
            $jsonContent = $this->serializer->serialize($examPapers, 'json', $context);

            return new JsonResponse([
                'examPapers' => json_decode($jsonContent, true)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting exam papers: ' . $e->getMessage());
            return $this->json(['error' => 'An error occurred while fetching exam papers'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}