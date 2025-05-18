<?php

namespace App\Controller;

use App\Service\LectureRecordingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
class LectureRecordingController extends AbstractController
{
    private LectureRecordingService $lectureRecordingService;

    public function __construct(LectureRecordingService $lectureRecordingService)
    {
        $this->lectureRecordingService = $lectureRecordingService;
    }

    #[Route('/api/lecture-recording/{filename}', name: 'get_lecture_recording', methods: ['GET'])]
    public function getRecording(string $filename, Request $request): Response
    {
        $uid = $request->query->get('uid', null);
        return $this->lectureRecordingService->getRecordingResponse($filename, $uid);
    }
}