<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WordUploadService
{
    private string $uploadDirectory;
    private ImageManager $imageManager;
    private string $audacityPath;

    public function __construct(
        private LoggerInterface $logger,
        private SluggerInterface $slugger
    ) {
        $this->uploadDirectory = dirname(__DIR__, 2) . '/public/assets/languages';
        $this->imageManager = new ImageManager(new Driver());
        $this->audacityPath = '/Applications/Audacity.app/Contents/MacOS/Audacity';
    }

    public function uploadAudio(UploadedFile $file, string $word): array
    {
        $this->logger->info('Starting audio upload for word: ' . $word);

        // Generate a unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Create audio directory if it doesn't exist
        $audioDirectory = $this->uploadDirectory . '/audio';
        if (!file_exists($audioDirectory)) {
            mkdir($audioDirectory, 0777, true);
        }

        // Create temporary directory for processing
        $tempDir = sys_get_temp_dir() . '/audio_processing_' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            // Move the file to temp directory first
            $tempFilePath = $tempDir . '/' . $newFilename;
            $file->move($tempDir, $newFilename);

            $noiseSample = $tempDir . '/noise_sample.wav';
            $noiseProfile = $tempDir . '/noise.prof';
            $cleanedFile = $audioDirectory . '/' . $newFilename;

            // Step 1: Generate noise profile from first 0.5 seconds
            $cmdExtract = sprintf('sox "%s" "%s" trim 0 0.5', $tempFilePath, $noiseSample);
            exec($cmdExtract, $out1, $ret1);
            if ($ret1 !== 0) {
                throw new \RuntimeException('Failed to extract noise sample for profile.');
            }

            // Generate noise profile
            $cmdProfile = sprintf('sox "%s" -n noiseprof "%s"', $noiseSample, $noiseProfile);
            exec($cmdProfile, $out2, $ret2);
            if ($ret2 !== 0) {
                throw new \RuntimeException('Failed to generate noise profile.');
            }

            // Step 2: Apply noise reduction
            $cmdReduce = sprintf('sox "%s" "%s" noisered "%s" 0.21', $tempFilePath, $cleanedFile, $noiseProfile);
            exec($cmdReduce, $out3, $ret3);
            if ($ret3 !== 0) {
                throw new \RuntimeException('Failed to apply noise reduction.');
            }

            $this->logger->info('Audio noise reduction completed successfully');

        } catch (\Exception $e) {
            $this->logger->error('Error processing audio: ' . $e->getMessage());
            throw $e;
        } finally {
            // Clean up temporary directory
            if (file_exists($tempDir)) {
                array_map('unlink', glob("$tempDir/*.*"));
                rmdir($tempDir);
            }
        }

        $this->logger->info('Audio upload completed successfully for word: ' . $word);

        return [
            'word' => $word,
            'filename' => $newFilename,
            'createdAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }

    public function uploadImage(UploadedFile $file, string $word): array
    {
        $this->logger->info('Starting image upload for word: ' . $word);

        // Generate a unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.jpg';

        // Create images directory if it doesn't exist
        $imagesDirectory = $this->uploadDirectory . '/images';
        if (!file_exists($imagesDirectory)) {
            mkdir($imagesDirectory, 0777, true);
        }

        // Process and compress the image
        $image = $this->imageManager->read($file->getPathname());
        $image->scaleDown(512); // Max width/height of 1920px
        $image->toJpeg(90); // 90% quality

        // Save the compressed image
        $image->save($imagesDirectory . '/' . $newFilename);

        $this->logger->info('Image upload completed successfully for word: ' . $word);

        return [
            'word' => $word,
            'filename' => $newFilename,
            'createdAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }

    public function getAudioFilePath(string $filename): ?string
    {
        $filePath = $this->uploadDirectory . '/audio/' . $filename;
        return file_exists($filePath) ? $filePath : null;
    }

    public function getImageFilePath(string $filename): ?string
    {
        $filePath = $this->uploadDirectory . '/images/' . $filename;
        return file_exists($filePath) ? $filePath : null;
    }

    public function removeAudio(string $filename): bool
    {
        try {
            $filePath = $this->getAudioFilePath($filename);
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error removing audio file: ' . $e->getMessage());
            return false;
        }
    }

    public function removeImage(string $filename): bool
    {
        try {
            $filePath = $this->getImageFilePath($filename);
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error removing image file: ' . $e->getMessage());
            return false;
        }
    }

    public function listAudios(): array
    {
        $audioDirectory = $this->uploadDirectory . '/audio';
        if (!file_exists($audioDirectory)) {
            return [];
        }

        $files = [];
        $audioFiles = glob($audioDirectory . '/*.{mp3,wav,ogg}', GLOB_BRACE);

        foreach ($audioFiles as $file) {
            $filename = basename($file);
            $files[] = [
                'filename' => $filename,
                'createdAt' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return $files;
    }

    public function listImages(): array
    {
        $imagesDirectory = $this->uploadDirectory . '/images';
        if (!file_exists($imagesDirectory)) {
            return [];
        }

        $files = [];
        $imageFiles = glob($imagesDirectory . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

        foreach ($imageFiles as $file) {
            $filename = basename($file);
            $files[] = [
                'filename' => $filename,
                'createdAt' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return $files;
    }
}