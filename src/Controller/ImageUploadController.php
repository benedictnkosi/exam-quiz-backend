<?php

namespace App\Controller;

use App\Service\ImageUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ImageUploadController extends AbstractController
{
    private $imageUploadService;
    private $logger;

    public function __construct(ImageUploadService $imageUploadService, LoggerInterface $logger)
    {
        $this->imageUploadService = $imageUploadService;
        $this->logger = $logger;
    }

    #[Route('/api/upload/image', name: 'upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        try {
            $this->logger->info('Starting image upload');
            $this->logger->debug('Request files: ' . json_encode($request->files->all()));
            $this->logger->debug('Request content: ' . $request->getContent());

            $file = $request->files->get('image');
            $imageName = $request->request->get('imageName');

            $this->logger->debug('File object: ' . ($file ? 'exists' : 'null'));
            $this->logger->debug('Image name: ' . ($imageName ?? 'null'));

            if (!$file) {
                return $this->json(['error' => 'No image file provided'], 400);
            }

            if (!$file->isValid()) {
                return $this->json(['error' => 'Invalid file upload: ' . $file->getErrorMessage()], 400);
            }

            if (!$imageName) {
                return $this->json(['error' => 'Image name is required'], 400);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                return $this->json(['error' => 'Invalid file type. Only JPEG, PNG, and GIF files are allowed.'], 400);
            }

            $filename = $this->imageUploadService->upload($file, $imageName);

            return $this->json([
                'status' => 'OK',
                'message' => 'Image uploaded successfully',
                'fileName' => $filename
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Upload error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error uploading image: ' . $e->getMessage()
            ], 500);
        }
    }
}