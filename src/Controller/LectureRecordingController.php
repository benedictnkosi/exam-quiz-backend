<?php

namespace App\Controller;

use App\Service\LectureRecordingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LectureRecordingController extends AbstractController
{
    private LectureRecordingService $lectureRecordingService;

    public function __construct(LectureRecordingService $lectureRecordingService)
    {
        $this->lectureRecordingService = $lectureRecordingService;
    }

    #[Route('/api/lecture-recording/{filename}', name: 'get_lecture_recording', methods: ['GET'])]
    public function getRecording(string $filename): Response
    {
        return $this->lectureRecordingService->getRecordingResponse($filename);
    }
}