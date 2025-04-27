<?php

namespace App\Controller;

use App\Service\TopicRecordingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TopicRecordingController extends AbstractController
{
    private TopicRecordingService $topicRecordingService;

    public function __construct(TopicRecordingService $topicRecordingService)
    {
        $this->topicRecordingService = $topicRecordingService;
    }

    #[Route('/api/topics/recordings/{subjectName}', name: 'get_topics_with_recordings', methods: ['GET'])]
    public function getTopicsWithRecordings(string $subjectName): JsonResponse
    {
        $topics = $this->topicRecordingService->findTopicsWithRecordings($subjectName);

        $response = array_map(function ($topic) {
            return [
                'recordingFileName' => $topic->getRecordingFileName(),
                'lecture_name' => $topic->getSubTopic(),
                'image' => $topic->getImageFileName()
            ];
        }, $topics);

        return $this->json([
            'status' => 'success',
            'data' => $response
        ]);
    }

    #[Route('/api/topics/recordings/{subjectName}/{subTopic}', name: 'get_recording_by_subtopic', methods: ['GET'])]
    public function getRecordingBySubTopic(string $subjectName, string $subTopic): JsonResponse
    {
        $topic = $this->topicRecordingService->findRecordingBySubTopic($subjectName, $subTopic);

        if (!$topic) {
            return $this->json([
                'status' => 'error',
                'message' => 'No recording found for the specified subject and subtopic'
            ], 404);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'recordingFileName' => $topic->getRecordingFileName(),
                'lecture_name' => $topic->getSubTopic(),
                'image' => $topic->getImageFileName()
            ]
        ]);
    }
}