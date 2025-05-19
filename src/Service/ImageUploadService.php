<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class ImageUploadService
{
    private $targetDirectory;
    private $slugger;
    private $logger;

    public function __construct(string $targetDirectory, SluggerInterface $slugger, LoggerInterface $logger)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
        $this->logger = $logger;
    }

    public function upload(UploadedFile $file, string $imageName): string
    {
        $this->logger->info('Starting file upload in service');

        // Validate file exists and is readable
        if (!$file->getPathname() || !is_readable($file->getPathname())) {
            throw new \Exception('The uploaded file is not readable');
        }

        $this->logger->debug('File info: ' . json_encode([
            'originalName' => $file->getClientOriginalName(),
            'mimeType' => $file->getMimeType(),
            'size' => $file->getSize(),
            'error' => $file->getError(),
            'path' => $file->getPathname()
        ]));

        if (!file_exists($this->targetDirectory)) {
            $this->logger->info('Creating upload directory: ' . $this->targetDirectory);
            mkdir($this->targetDirectory, 0777, true);
        }

        // Use the exact provided imageName
        $safeFilename = $this->slugger->slug(pathinfo($imageName, PATHINFO_FILENAME));
        $extension = pathinfo($imageName, PATHINFO_EXTENSION) ?: $file->guessExtension();
        $newFilename = $safeFilename . '.' . $extension;
        $this->logger->debug('Generated filename: ' . $newFilename);

        try {
            // Validate file size (5MB limit)
            $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
            if ($file->getSize() > $maxFileSize) {
                throw new \Exception('File size exceeds the maximum limit of 5MB');
            }

            // Get the temporary file path
            $tempPath = $file->getPathname();

            // Verify the file is still readable
            if (!is_readable($tempPath)) {
                throw new \Exception('Cannot read the uploaded file');
            }

            // Create image resource based on file type
            switch ($file->getMimeType()) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($tempPath);
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($tempPath);
                    break;
                case 'image/gif':
                    $source = imagecreatefromgif($tempPath);
                    break;
                default:
                    throw new \Exception('Unsupported image format');
            }

            if (!$source) {
                throw new \Exception('Failed to create image resource');
            }

            // Get original dimensions
            $width = imagesx($source);
            $height = imagesy($source);

            // Calculate new dimensions (max width 800px while maintaining aspect ratio)
            $maxWidth = 800;
            $newWidth = min($width, $maxWidth);
            $newHeight = ($newWidth / $width) * $height;

            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($file->getMimeType() === 'image/png') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image
            imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save compressed image with higher compression
            $targetPath = $this->targetDirectory . '/' . $newFilename;
            switch ($file->getMimeType()) {
                case 'image/jpeg':
                    imagejpeg($newImage, $targetPath, 50); // Reduced quality to 50%
                    break;
                case 'image/png':
                    // Convert PNG to JPEG for better compression
                    $jpegPath = $this->targetDirectory . '/' . pathinfo($newFilename, PATHINFO_FILENAME) . '.jpg';
                    imagejpeg($newImage, $jpegPath, 50);
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                    $newFilename = pathinfo($newFilename, PATHINFO_FILENAME) . '.jpg';
                    $targetPath = $jpegPath;
                    break;
                case 'image/gif':
                    imagegif($newImage, $targetPath);
                    break;
            }

            // Free up memory
            imagedestroy($source);
            imagedestroy($newImage);

            // Verify the compressed file size
            $compressedSize = filesize($targetPath);
            $this->logger->info('Original size: ' . $file->getSize() . ' bytes, Compressed size: ' . $compressedSize . ' bytes');

            $this->logger->info('File compressed and saved successfully');
            return $newFilename;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process image: ' . $e->getMessage());
            throw new \Exception('Failed to upload image: ' . $e->getMessage());
        }
    }
}