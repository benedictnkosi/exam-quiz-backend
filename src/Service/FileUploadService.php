<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private string $uploadDirectory;

    public function __construct(
        private SluggerInterface $slugger,
        string $projectDir
    ) {
        $this->uploadDirectory = $projectDir . '/public/uploads/documents/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }

    public function uploadDocument(UploadedFile $file, int $companyId, string $documentType): string
    {
        // Validate file
        $this->validateFile($file);

        // Generate secure filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->getClientOriginalExtension();
        
        // Create filename with company ID and document type
        $filename = sprintf(
            '%s_%s_%s_%s.%s',
            $companyId,
            $documentType,
            $safeFilename,
            uniqid(),
            $extension
        );

        // Move file to upload directory
        $file->move($this->uploadDirectory, $filename);

        // Return the filename (relative path for database storage)
        return 'uploads/documents/' . $filename;
    }

    private function validateFile(UploadedFile $file): void
    {
        // Check file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File size must be less than 10MB');
        }

        // Check file type
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \InvalidArgumentException('Only PDF and image files are allowed');
        }

        // Check for malicious files
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload');
        }
    }

    public function deleteDocument(string $filename): bool
    {
        $filePath = $this->uploadDirectory . basename($filename);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return false;
    }

    public function getDocumentUrl(string $filename): string
    {
        return '/uploads/documents/' . basename($filename);
    }
} 