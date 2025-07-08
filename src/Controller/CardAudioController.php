<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api/cards/audio')]
class CardAudioController extends AbstractController
{
    private string $audioDirectory;

    public function __construct()
    {
        $this->audioDirectory = dirname(__DIR__, 2) . '/public/assets/cards/audio';
        if (!file_exists($this->audioDirectory)) {
            mkdir($this->audioDirectory, 0777, true);
        }
    }

    #[Route('/upload', name: 'card_audio_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }
        if (!$file->isValid()) {
            return $this->json(['error' => 'Invalid file upload'], Response::HTTP_BAD_REQUEST);
        }
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->guessExtension() ?: 'mp3';
        $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;
        try {
            $file->move($this->audioDirectory, $newFilename);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to save file: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $this->json([
            'message' => 'Audio file uploaded successfully',
            'filename' => $newFilename
        ]);
    }

    #[Route('/download/{filename}', name: 'card_audio_download', methods: ['GET'])]
    public function download(string $filename): Response
    {
        $filePath = $this->audioDirectory . '/' . $filename;
        if (!file_exists($filePath)) {
            return new JsonResponse(['error' => 'Audio file not found'], Response::HTTP_NOT_FOUND);
        }
        $mimeType = mime_content_type($filePath);
        return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_ATTACHMENT, ['Content-Type' => $mimeType]);
    }
} 