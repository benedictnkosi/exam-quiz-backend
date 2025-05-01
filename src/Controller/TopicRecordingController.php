<?php

namespace App\Controller;

use App\Service\TopicRecordingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class TopicRecordingController extends AbstractController
{
    private TopicRecordingService $topicRecordingService;
    private LoggerInterface $logger;

    public function __construct(TopicRecordingService $topicRecordingService, LoggerInterface $logger)
    {
        $this->topicRecordingService = $topicRecordingService;
        $this->logger = $logger;
    }

    #[Route('/api/topics/recordings/{subjectName}', name: 'get_topics_with_recordings', methods: ['GET'])]
    public function getTopicsWithRecordings(string $subjectName, Request $request): JsonResponse
    {
        $uid = $request->query->get('uid');
        $this->logger->info("uid: " . $uid);
        $topics = $this->topicRecordingService->findTopicsWithRecordings($uid ?? 'default', $subjectName);

        $response = array_map(function ($topic) {
            $imageSearch = null;
            $lecture = $topic->getLecture();

            if ($lecture) {
                // Extract the image search substring
                if (preg_match('/\[Image Search: (.*?)\]/', $lecture, $matches)) {
                    $imageSearch = $matches[1];
                }
            }

            return [
                'recordingFileName' => $topic->getRecordingFileName(),
                'lecture_name' => $topic->getSubTopic(),
                'image' => $topic->getImageFileName(),
                'main_topic' => $topic->getName(),
                'id' => $topic->getId(),
                'image_search' => $imageSearch
            ];
        }, $topics);

        return $this->json([
            'status' => 'success',
            'data' => $response
        ]);
    }

    #[Route('/api/topics/recordings/{subjectName}/{subTopic}', name: 'get_recording_by_subtopic', methods: ['GET'])]
    public function getRecordingBySubTopic(string $subjectName, string $subTopic, Request $request): JsonResponse
    {
        $grade = $request->query->get('grade');
        $topic = $this->topicRecordingService->findRecordingBySubTopic($subjectName, $subTopic, $grade);

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
                'main_topic' => $topic->getName(),
                'image' => $topic->getImageFileName()
            ]
        ]);
    }

    #[Route('/api/topics/recording/{topicId}', name: 'get_recording_by_topic_id', methods: ['GET'])]
    public function getRecordingByTopicId(int $topicId): JsonResponse
    {
        $topic = $this->topicRecordingService->findRecordingByTopicId($topicId);

        if (!$topic) {
            return $this->json([
                'status' => 'error',
                'message' => 'No recording found for the specified topic ID'
            ], 404);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'recordingFileName' => $topic->getRecordingFileName(),
                'lecture_name' => $topic->getSubTopic(),
                'main_topic' => $topic->getName(),
                'image' => $topic->getImageFileName()
            ]
        ]);
    }
}