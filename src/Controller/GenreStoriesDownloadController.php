<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Psr\Log\LoggerInterface;

#[Route('/api/genre-stories')]
class GenreStoriesDownloadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    #[Route('/download', name: 'download_books', methods: ['GET'])]
    public function downloadBooks(): Response
    {
        try {
            $this->logger->info('Starting books download');
            
            $filePath = $this->getParameter('kernel.project_dir') . '/public/assets/books.json';
            
            if (!file_exists($filePath)) {
                $this->logger->warning('Books file not found', ['filePath' => $filePath]);
                return new JsonResponse(['error' => 'Books file not found'], Response::HTTP_NOT_FOUND);
            }

            $mimeType = 'application/json';
            
            return $this->file(
                $filePath, 
                'genre_stories.json', 
                ResponseHeaderBag::DISPOSITION_ATTACHMENT, 
                ['Content-Type' => $mimeType]
            );
            
        } catch (\Exception $e) {
            $this->logger->error('Error downloading books: ' . $e->getMessage());
            return new JsonResponse(
                ['error' => 'An error occurred while downloading the books file'], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
} 