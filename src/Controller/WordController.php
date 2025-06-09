<?php

namespace App\Controller;

use App\Entity\Word;
use App\Service\WordManagementService;
use App\Service\WordUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/word')]
class WordController extends AbstractController
{
    private EntityManagerInterface $em;
    private WordManagementService $wordService;
    private WordUploadService $uploadService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        WordManagementService $wordService,
        WordUploadService $uploadService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->wordService = $wordService;
        $this->uploadService = $uploadService;
        $this->logger = $logger;
    }

    #[Route('/image/upload', name: 'word_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request, ValidatorInterface $validator): JsonResponse
    {
        try {
            $wordId = $request->query->get('word');
            if (!$wordId) {
                return $this->json(['error' => 'Word ID is required'], 400);
            }

            $word = $this->em->getRepository(Word::class)->find($wordId);
            if (!$word) {
                return $this->json(['error' => 'Word not found'], 404);
            }

            $file = $request->files->get('image');
            if (!$file) {
                return $this->json(['error' => 'No image uploaded'], 400);
            }

            // Validate file
            $violations = $validator->validate($file, [
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/png',
                        'image/gif'
                    ],
                    'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or GIF)',
                ])
            ]);

            if (count($violations) > 0) {
                return $this->json(['error' => $violations[0]->getMessage()], 400);
            }

            // Upload the image
            $result = $this->uploadService->uploadImage($file, $wordId);

            // Update word with new image path
            $this->wordService->setWordImage($wordId, $result['filename']);

            return $this->json([
                'message' => 'Image uploaded successfully',
                'imagePath' => $result['filename']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error uploading word image: ' . $e->getMessage());
            return $this->json(['error' => 'Error uploading image'], 500);
        }
    }
}