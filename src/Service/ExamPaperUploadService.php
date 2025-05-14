<?php

namespace App\Service;

use App\Entity\ExamPaper;
use App\Repository\ExamPaperRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Grade;
use App\Entity\Subject;
use App\Entity\Question;

class ExamPaperUploadService
{
    private string $uploadDir;
    private LoggerInterface $logger;

    public function __construct(
        private ExamPaperRepository $examPaperRepository,
        private SluggerInterface $slugger,
        private OpenAIService $openAiService,
        private ParameterBagInterface $params,
        private ExamPaperProcessorService $processorService,
        private EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->uploadDir = $this->params->get('upload_directory');
        $this->logger = $logger;
    }

    private function checkQuestionCount(string $subjectName, int $grade, int $year, string $term): void
    {
        // Find the subject by name and grade
        $gradeEntity = $this->entityManager->getRepository(Grade::class)->findOneBy(['number' => $grade]);
        if (!$gradeEntity) {
            throw new \InvalidArgumentException("Grade {$grade} not found");
        }

        $subject = $this->entityManager->getRepository(Subject::class)->findOneBy([
            'name' => $subjectName,
            'grade' => $gradeEntity
        ]);

        if (!$subject) {
            throw new \InvalidArgumentException("Subject {$subjectName} not found for grade {$grade}");
        }

        // Count questions for this subject, year, and term
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(q.id)')
            ->from(Question::class, 'q')
            ->where('q.subject = :subject')
            ->andWhere('q.year = :year')
            ->andWhere('q.term = :term')
            ->andWhere('q.active = :active')
            ->setParameter('subject', $subject)
            ->setParameter('year', $year)
            ->setParameter('term', $term)
            ->setParameter('active', true);

        $questionCount = $qb->getQuery()->getSingleScalarResult();

        if ($questionCount >= 5) {
            throw new \InvalidArgumentException('Maximum number of questions (5) already reached for this subject, grade, year, and term combination.');
        }
    }

    public function uploadPaper(
        UploadedFile $file,
        string $type,
        string $subjectName,
        int $grade,
        int $year,
        string $term,
        string $userUid,
        ?string $examPaperId = null
    ): ExamPaper {
        // Validate file type
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            throw new \InvalidArgumentException('Invalid file type. Only PDF, JPEG, and PNG files are allowed.');
        }

        // Validate grade
        if ($grade < 8 || $grade > 12) {
            throw new \InvalidArgumentException('Grade must be between 8 and 12');
        }

        // Validate year
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Year must be between 2000 and 2100');
        }

        // Validate term
        $validTerms = ['1', '2', '3', '4'];
        if (!in_array($term, $validTerms)) {
            throw new \InvalidArgumentException('Term must be one of: ' . implode(', ', $validTerms));
        }

        // Check question count limit if not a memo
        if ($type !== 'memo') {
            $this->checkQuestionCount($subjectName, $grade, $year, $term);
        }

        // Find user by UID
        $user = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $userUid]);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        // Generate unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Upload to OpenAI and get file ID
        $openAiResponse = $this->openAiService->uploadFile($file);
        $openAiFileId = $openAiResponse['id'] ?? null;

        if (!$openAiFileId) {
            throw new \Exception('Failed to get file ID from OpenAI response');
        }

        // Move file to upload directory
        $file->move($this->uploadDir, $newFilename);

        if ($type === 'memo') {
            if (!$examPaperId) {
                throw new \InvalidArgumentException('examPaperId is required for memo uploads');
            }

            // Find existing exam paper
            $examPaper = $this->examPaperRepository->find($examPaperId);
            if (!$examPaper) {
                throw new \InvalidArgumentException('Exam paper not found');
            }

            // Update memo information
            $examPaper->setMemoName($newFilename);
            $examPaper->setMemoOpenAiFileId($openAiFileId);
        } else {
            // Create new exam paper entity
            $examPaper = new ExamPaper();
            $examPaper->setSubjectName($subjectName);
            $examPaper->setGrade($grade);
            $examPaper->setYear($year);
            $examPaper->setTerm($term);
            $examPaper->setMemoName(''); // Initialize with empty string
            $examPaper->setPaperName(''); // Initialize with empty string
            $examPaper->setNumberOfQuestions(0); // Initialize with 0 questions
            $examPaper->setCurrentQuestion('0'); // Initialize current question as string
            $examPaper->setStatus('pending'); // Set initial status
            $examPaper->setUser($user); // Set the user
            $examPaper->setCreated(new \DateTime()); // Set creation time

            $examPaper->setPaperName($newFilename);
            $examPaper->setImages(null);
            $examPaper->setPaperOpenAiFileId($openAiFileId);
        }

        // Save to database
        $this->examPaperRepository->save($examPaper, true);

        // Process the paper if it's not a memo
        if ($type !== 'memo') {
            try {
                $this->processorService->processPaper($examPaper);
            } catch (\Exception $e) {
                $this->logger->error('Error processing paper: ' . $e->getMessage());
                // Don't throw the error, just log it and continue
            }
        }

        return $examPaper;
    }

    public function getExamPaperRepository(): ExamPaperRepository
    {
        return $this->examPaperRepository;
    }

    public function uploadImages(array $files, ExamPaper $examPaper): ExamPaper
    {
        $this->logger->info('Uploading images for exam paper: ' . $examPaper->getId());
        $uploadedImages = [];

        // Ensure the upload directory exists
        $imagesDir = $this->uploadDir . '/images';
        if (!file_exists($imagesDir)) {
            mkdir($imagesDir, 0777, true);
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            try {
                // Generate a unique filename
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                // Ensure the file is valid and readable
                if (!$file->isValid()) {
                    throw new \Exception('Invalid file upload: ' . $file->getErrorMessage());
                }

                // Move the file to the images directory
                $file->move($imagesDir, $newFilename);

                // Verify the file was moved successfully
                $filePath = $imagesDir . '/' . $newFilename;
                if (!file_exists($filePath)) {
                    throw new \Exception('Failed to move uploaded file');
                }

                // Add to uploaded images array
                $uploadedImages[] = $newFilename;
            } catch (\Exception $e) {
                // Log the error and continue with other files
                error_log('Error uploading file: ' . $e->getMessage());
                continue;
            }
        }

        if (empty($uploadedImages)) {
            throw new \Exception('No files were successfully uploaded');
        }

        // Get existing images or initialize empty array
        $existingImages = $examPaper->getImages() ?? [];
        if (!is_array($existingImages)) {
            $existingImages = [];
        }

        // Add new images to the array
        foreach ($uploadedImages as $index => $image) {
            $existingImages[$index] = $image;
        }

        // Update the exam paper with the new images array
        $examPaper->setImages($existingImages);

        // Save the exam paper
        $this->examPaperRepository->save($examPaper, true);

        return $examPaper;
    }

    public function getUploadDirectory(): string
    {
        return $this->uploadDir;
    }

    public function getImageFilePath(string $imageName): ?string
    {
        $imagesDir = $this->uploadDir . '/images';
        $filePath = $imagesDir . '/' . $imageName;
        return file_exists($filePath) ? $filePath : null;
    }

    public function uploadSingleImage(UploadedFile $file): string
    {
        $this->logger->info('Uploading single image');

        // Ensure the upload directory exists
        $imagesDir = $this->uploadDir;
        if (!file_exists($imagesDir)) {
            mkdir($imagesDir, 0777, true);
        }

        // Generate a unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Ensure the file is valid and readable
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload: ' . $file->getErrorMessage());
        }

        // Move the file to the images directory
        $file->move($imagesDir, $newFilename);

        // Verify the file was moved successfully
        $filePath = $imagesDir . '/' . $newFilename;
        if (!file_exists($filePath)) {
            throw new \Exception('Failed to move uploaded file');
        }

        return $newFilename;
    }

    public function removeImage(ExamPaper $examPaper, string $questionNumber): bool
    {
        $this->logger->info('Removing image for question: ' . $questionNumber);

        // Get existing images
        $images = $examPaper->getImages() ?? [];
        if (!is_array($images)) {
            $images = [];
        }

        // Check if image exists for this question
        if (!isset($images[$questionNumber])) {
            return false;
        }

        // Get the image filename
        $imageFilename = $images[$questionNumber];

        // Remove the image file from the filesystem
        $imagesDir = $this->uploadDir . '/images';
        $filePath = $imagesDir . '/' . $imageFilename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Remove the image from the array
        unset($images[$questionNumber]);

        // Update the exam paper
        $examPaper->setImages($images);

        // Save the exam paper
        $this->examPaperRepository->save($examPaper, true);

        return true;
    }
}