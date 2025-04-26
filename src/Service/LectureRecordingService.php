<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class LectureRecordingService
{
    private string $lecturesDirectory;

    public function __construct(ParameterBagInterface $params)
    {
        $this->lecturesDirectory = $params->get('kernel.project_dir') . '/public/assets/lectures';
    }

    public function getRecordingResponse(string $filename): Response
    {
        $filePath = $this->lecturesDirectory . '/' . $filename;

        if (!file_exists($filePath)) {
            return new Response('Recording not found', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'audio/ogg'); // Opus files use audio/ogg MIME type
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $filename
        );

        return $response;
    }
}