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

        $newFilename = uniqid() . '.' . $file->guessExtension();
        $this->logger->debug('Generated filename: ' . $newFilename);

        try {
            $this->logger->debug('Moving file to: ' . $this->targetDirectory . '/' . $newFilename);
            $file->move(
                $this->targetDirectory,
                $newFilename
            );
            $this->logger->info('File moved successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to move file: ' . $e->getMessage());
            throw new \Exception('Failed to upload image: ' . $e->getMessage());
        }

        return $newFilename;
    }
}