<?php

namespace App\Controller;

use App\Service\WordUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/api/word')]
class WordUploadController extends AbstractController
{
    public function __construct(
        private WordUploadService $uploadService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/audio/upload', name: 'app_word_audio_upload', methods: ['POST'])]
    public function uploadAudio(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('file');
            if (!$file) {
                return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
            }

            $word = $request->request->get('word');
            if (!$word) {
                return $this->json(['error' => 'Word is required'], Response::HTTP_BAD_REQUEST);
            }

            // Validate the file
            $violations = $this->validator->validate($file, [
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'audio/mpeg',
                        'audio/mp3',
                        'audio/m4a',
                        'audio/wav',
                        'audio/ogg',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid audio file (MP3, WAV, or OGG)',
                ])
            ]);

            if (count($violations) > 0) {
                return $this->json(['error' => (string) $violations], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->uploadService->uploadAudio($file, $word);

            return new JsonResponse([
                'message' => 'Audio file uploaded successfully',
                'wordAudio' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error uploading audio file: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/image/upload', name: 'app_word_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('file');
            if (!$file) {
                return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
            }

            $word = $request->request->get('word');
            if (!$word) {
                return $this->json(['error' => 'Word is required'], Response::HTTP_BAD_REQUEST);
            }

            // Validate the file
            $violations = $this->validator->validate($file, [
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or GIF)',
                ])
            ]);

            if (count($violations) > 0) {
                return $this->json(['error' => (string) $violations], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->uploadService->uploadImage($file, $word);

            return new JsonResponse([
                'message' => 'Image uploaded successfully',
                'wordImage' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error uploading image: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/audio/get/{filename}', name: 'app_word_audio_get', methods: ['GET'])]
    public function getAudio(string $filename): Response
    {
        try {
            $filePath = $this->uploadService->getAudioFilePath($filename);
            if (!$filePath) {
                return new JsonResponse(['error' => 'Audio file not found'], Response::HTTP_NOT_FOUND);
            }

            $mimeType = mime_content_type($filePath);
            return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_ATTACHMENT, ['Content-Type' => $mimeType]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting audio file: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/image/get/{filename}', name: 'app_word_image_get', methods: ['GET'])]
    public function getImage(string $filename): Response
    {
        try {
            $filePath = $this->uploadService->getImageFilePath($filename);
            if (!$filePath) {
                return new JsonResponse(['error' => 'Image not found'], Response::HTTP_NOT_FOUND);
            }

            $mimeType = mime_content_type($filePath);
            return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_INLINE, ['Content-Type' => $mimeType]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting image: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/audio/list', name: 'app_word_audio_list', methods: ['GET'])]
    public function listAudios(): JsonResponse
    {
        try {
            $files = $this->uploadService->listAudios();

            return new JsonResponse([
                'wordAudios' => $files
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error listing audio files: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/image/list', name: 'app_word_image_list', methods: ['GET'])]
    public function listImages(): JsonResponse
    {
        try {
            $files = $this->uploadService->listImages();

            return new JsonResponse([
                'wordImages' => $files
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error listing images: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}