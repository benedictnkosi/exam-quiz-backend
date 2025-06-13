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

    public function uploadAudio(UploadedFile $file, ?float $startTime = null, ?float $endTime = null): array
    {
        $this->logger->info('Starting audio upload');

        // Generate a unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.mp3'; // Always use .mp3 extension

        // Create audio directory if it doesn't exist
        $audioDirectory = $this->uploadDirectory . '/audio';
        if (!file_exists($audioDirectory)) {
            mkdir($audioDirectory, 0777, true);
        }

        // Define final file path early
        $finalFile = $audioDirectory . '/' . $newFilename;

        // Create temporary directory for processing
        $tempDir = sys_get_temp_dir() . '/audio_processing_' . uniqid();
        mkdir($tempDir, 0777, true);

        try {
            // First, ensure we have a valid file to work with
            if (!$file->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            // Get the original file path
            $originalPath = $file->getPathname();
            if (!file_exists($originalPath)) {
                throw new \Exception('Original file not found');
            }

            // Copy the file to temp directory first
            $tempFilePath = $tempDir . '/' . $newFilename;
            if (!copy($originalPath, $tempFilePath)) {
                throw new \Exception('Failed to copy file to temporary directory');
            }

            // Convert WebM to MP3 if needed
            $isWebM = in_array($file->getMimeType(), ['audio/webm', 'video/webm']);
            if ($isWebM) {
                $webmFile = $tempFilePath;
                $mp3File = $tempDir . '/converted_' . $newFilename;

                // Convert WebM to MP3 using ffmpeg
                $cmdConvert = sprintf(
                    'ffmpeg -i "%s" -vn -acodec libmp3lame -q:a 2 "%s"',
                    $webmFile,
                    $mp3File
                );

                exec($cmdConvert, $outConvert, $retConvert);
                if ($retConvert === 0 && file_exists($mp3File)) {
                    unlink($webmFile); // Remove the original WebM file
                    $tempFilePath = $mp3File;
                } else {
                    $this->logger->warning('Failed to convert WebM to MP3, proceeding with original file');
                }
            }

            $noiseSample = $tempDir . '/noise_sample.wav';
            $noiseProfile = $tempDir . '/noise.prof';
            $cleanedFile = $tempDir . '/cleaned_' . $newFilename;

            // Step 1: Generate noise profile from first 0.5 seconds
            $cmdExtract = sprintf('sox "%s" "%s" trim 0 0.5', $tempFilePath, $noiseSample);
            exec($cmdExtract, $out1, $ret1);
            if ($ret1 !== 0) {
                $this->logger->warning('Failed to extract noise sample, proceeding with original file');
                copy($tempFilePath, $cleanedFile);
            } else {
                // Generate noise profile
                $cmdProfile = sprintf('sox "%s" -n noiseprof "%s"', $noiseSample, $noiseProfile);
                exec($cmdProfile, $out2, $ret2);
                if ($ret2 !== 0) {
                    $this->logger->warning('Failed to generate noise profile, proceeding with original file');
                    copy($tempFilePath, $cleanedFile);
                } else {
                    // Step 2: Apply noise reduction
                    $cmdReduce = sprintf('sox "%s" "%s" noisered "%s" 0.21', $tempFilePath, $cleanedFile, $noiseProfile);
                    exec($cmdReduce, $out3, $ret3);
                    if ($ret3 !== 0) {
                        $this->logger->warning('Failed to apply noise reduction, proceeding with original file');
                        copy($tempFilePath, $cleanedFile);
                    }
                }
            }

            // Step 3: Trim the audio if start and end times are provided
            if ($startTime !== null) {
                $trimmedFile = $tempDir . '/trimmed_' . $newFilename;

                // If endTime is not provided, get the duration of the audio file
                if ($endTime === null) {
                    $durationCmd = sprintf('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"', $cleanedFile);
                    exec($durationCmd, $durationOutput, $durationRet);
                    if ($durationRet === 0 && isset($durationOutput[0])) {
                        $endTime = (float) $durationOutput[0];
                    } else {
                        $this->logger->warning('Failed to get audio duration, using cleaned file');
                        copy($cleanedFile, $finalFile);
                        return [
                            'filename' => $newFilename,
                            'createdAt' => (new \DateTime())->format('Y-m-d H:i:s')
                        ];
                    }
                }

                $duration = $endTime - $startTime;

                // Use ffmpeg to trim the audio
                $cmdTrim = sprintf(
                    'ffmpeg -i "%s" -ss %.3f -t %.3f -c copy "%s"',
                    $cleanedFile,
                    $startTime,
                    $duration,
                    $trimmedFile
                );

                exec($cmdTrim, $out4, $ret4);
                if ($ret4 === 0 && file_exists($trimmedFile)) {
                    copy($trimmedFile, $finalFile);
                } else {
                    $this->logger->warning('Failed to trim audio, using cleaned file');
                    copy($cleanedFile, $finalFile);
                }
            } else {
                copy($cleanedFile, $finalFile);
            }

            $this->logger->info('Audio processing completed successfully');

        } catch (\Exception $e) {
            $this->logger->error('Error processing audio: ' . $e->getMessage());

            // Try to save the original file as a last resort
            try {
                if (file_exists($originalPath)) {
                    copy($originalPath, $finalFile);
                } else {
                    throw new \Exception('Could not save the audio file: original file not found');
                }
            } catch (\Exception $innerException) {
                throw new \Exception('Failed to process audio file: ' . $e->getMessage() . ' (Fallback also failed: ' . $innerException->getMessage() . ')');
            }
        } finally {
            // Clean up temporary directory
            if (file_exists($tempDir)) {
                array_map('unlink', glob("$tempDir/*.*"));
                rmdir($tempDir);
            }
        }

        $this->logger->info('Audio upload completed successfully');

        return [
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
        $newFilename = $safeFilename . '-' . uniqid() . '.png';

        // Create images directory if it doesn't exist
        $imagesDirectory = $this->uploadDirectory . '/images';
        if (!file_exists($imagesDirectory)) {
            mkdir($imagesDirectory, 0777, true);
        }

        // Process the image while preserving transparency
        $image = $this->imageManager->read($file->getPathname());
        $image->scaleDown(width: 200); // Reduce width to 200px while maintaining aspect ratio

        // Optimize the image size while preserving transparency
        $image->save($imagesDirectory . '/' . $newFilename, quality: 80);

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
        $audioFiles = glob($audioDirectory . '/*.{mp3,wav,ogg,m4a}', GLOB_BRACE);

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