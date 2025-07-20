<?php

namespace App\Controller;

use App\Service\ImageUploadService;
use App\Service\ShopImageUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ImageUploadController extends AbstractController
{
    private $imageUploadService;
    private $shopImageUploadService;
    private $logger;

    public function __construct(ImageUploadService $imageUploadService, ShopImageUploadService $shopImageUploadService, LoggerInterface $logger)
    {
        $this->imageUploadService = $imageUploadService;
        $this->shopImageUploadService = $shopImageUploadService;
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

    #[Route('/api/upload/shop-image', name: 'upload_shop_image', methods: ['POST'])]
    public function uploadShopImage(Request $request): JsonResponse
    {
        try {
            $this->logger->info('Starting shop image upload');
            $this->logger->debug('Request files: ' . json_encode($request->files->all()));
            $this->logger->debug('Request content: ' . $request->getContent());

            $file = $request->files->get('image');

            $this->logger->debug('File object: ' . ($file ? 'exists' : 'null'));

            if (!$file) {
                return $this->json(['error' => 'No image file provided'], 400);
            }

            if (!$file->isValid()) {
                return $this->json(['error' => 'Invalid file upload: ' . $file->getErrorMessage()], 400);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                return $this->json(['error' => 'Invalid file type. Only JPEG, PNG, and GIF files are allowed.'], 400);
            }

            $filename = $this->shopImageUploadService->upload($file);

            return $this->json([
                'status' => 'OK',
                'message' => 'Shop image uploaded successfully',
                'fileName' => $filename
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Shop upload error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error uploading shop image: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/shop-image/{filename}', name: 'get_shop_image', methods: ['GET'])]
    public function getShopImage(string $filename): Response
    {
        try {
            $this->logger->info('Requesting shop image: ' . $filename);

            // Validate filename format (timestamp_randomstring.extension)
            if (!preg_match('/^\d+_[a-f0-9]{32}\.[a-zA-Z0-9]+$/', $filename)) {
                $this->logger->warning('Invalid filename format: ' . $filename);
                return $this->json(['error' => 'Invalid filename format'], 400);
            }

            // Build the file path
            $filePath = $this->getParameter('kernel.project_dir') . '/public/assets/images/shop/' . $filename;

            // Check if file exists
            if (!file_exists($filePath)) {
                $this->logger->warning('Shop image not found: ' . $filePath);
                return $this->json(['error' => 'Image not found'], 404);
            }

            // Get file info
            $fileInfo = pathinfo($filePath);
            $extension = strtolower($fileInfo['extension']);

            // Set appropriate content type
            $contentTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            ];

            $contentType = $contentTypes[$extension] ?? 'application/octet-stream';

            // Create response with proper headers
            $response = new BinaryFileResponse($filePath);
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Cache-Control', 'public, max-age=31536000'); // Cache for 1 year
            $response->headers->set('Access-Control-Allow-Origin', '*');

            $this->logger->info('Shop image served successfully: ' . $filename);
            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error serving shop image: ' . $e->getMessage());
            return $this->json([
                'error' => 'Error serving image: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/shop-image/{filename}', name: 'delete_shop_image', methods: ['DELETE'])]
    public function deleteShopImage(string $filename): JsonResponse
    {
        try {
            $this->logger->info('Attempting to delete shop image: ' . $filename);

            // Validate filename format (timestamp_randomstring.extension)
            if (!preg_match('/^\d+_[a-f0-9]{32}\.[a-zA-Z0-9]+$/', $filename)) {
                $this->logger->warning('Invalid filename format for deletion: ' . $filename);
                return $this->json(['error' => 'Invalid filename format'], 400);
            }

            // Build the file path
            $filePath = $this->getParameter('kernel.project_dir') . '/public/assets/images/shop/' . $filename;

            // Check if file exists
            if (!file_exists($filePath)) {
                $this->logger->warning('Shop image not found for deletion: ' . $filePath);
                return $this->json(['error' => 'Image not found'], 404);
            }

            // Check if file is actually a file (not a directory)
            if (!is_file($filePath)) {
                $this->logger->warning('Path is not a file: ' . $filePath);
                return $this->json(['error' => 'Invalid file path'], 400);
            }

            // Attempt to delete the file
            if (unlink($filePath)) {
                $this->logger->info('Shop image deleted successfully: ' . $filename);
                return $this->json([
                    'status' => 'OK',
                    'message' => 'Image deleted successfully',
                    'fileName' => $filename
                ]);
            } else {
                $this->logger->error('Failed to delete file: ' . $filePath);
                return $this->json([
                    'status' => 'NOK',
                    'message' => 'Failed to delete image'
                ], 500);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error deleting shop image: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error deleting image: ' . $e->getMessage()
            ], 500);
        }
    }
}