<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class ShopImageUploadService
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

    public function upload(UploadedFile $file): string
    {
        $this->logger->info('Starting shop image upload');

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
            $this->logger->info('Creating shop upload directory: ' . $this->targetDirectory);
            mkdir($this->targetDirectory, 0777, true);
        }

        // Generate a random filename
        $extension = $file->guessExtension();
        $randomString = bin2hex(random_bytes(16)); // 32 character hex string
        $timestamp = time();
        $newFilename = $timestamp . '_' . $randomString . '.' . $extension;
        $this->logger->debug('Generated filename: ' . $newFilename);

        try {
            // Validate file size (10MB limit for shop images)
            $maxFileSize = 10 * 1024 * 1024; // 10MB in bytes
            if ($file->getSize() > $maxFileSize) {
                throw new \Exception('File size exceeds the maximum limit of 10MB');
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

            // Calculate new dimensions (max width 512px while maintaining aspect ratio)
            $maxWidth = 512;
            $newWidth = $width;
            $newHeight = $height;

            // Only resize if width is greater than 512px
            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = ($newWidth / $width) * $height;
            }

            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($file->getMimeType() === 'image/png') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image if needed
            if ($width > $maxWidth) {
                imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            } else {
                // Copy original image without resizing
                imagecopy($newImage, $source, 0, 0, 0, 0, $width, $height);
            }

            // Save image with appropriate compression
            $targetPath = $this->targetDirectory . '/' . $newFilename;
            switch ($file->getMimeType()) {
                case 'image/jpeg':
                    // Save as JPEG with good quality
                    imagejpeg($newImage, $targetPath, 85);
                    break;
                case 'image/png':
                    // Save as PNG with good compression
                    imagepng($newImage, $targetPath, 6);
                    break;
                case 'image/gif':
                    // Save as GIF
                    imagegif($newImage, $targetPath);
                    break;
                default:
                    throw new \Exception('Unsupported image format');
            }

            // Free up memory
            imagedestroy($source);
            imagedestroy($newImage);

            // Verify the file size
            $finalSize = filesize($targetPath);
            $this->logger->info('Original size: ' . $file->getSize() . ' bytes, Final size: ' . $finalSize . ' bytes');

            $this->logger->info('Shop image uploaded successfully');
            return $newFilename;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process shop image: ' . $e->getMessage());
            throw new \Exception('Failed to upload shop image: ' . $e->getMessage());
        }
    }
} 