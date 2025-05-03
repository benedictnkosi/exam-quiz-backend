<?php

namespace App\Controller;

use App\Entity\Topic;
use App\Service\TopicService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/topics')]
class TopicController extends AbstractController
{
    public function __construct(
        private TopicService $topicService
    ) {
    }

    #[Route('', name: 'get_all_topics', methods: ['GET'])]
    public function getAllTopics(): JsonResponse
    {
        try {
            $topics = $this->topicService->getAllTopics();

            return new JsonResponse([
                'topics' => array_map(function (Topic $topic) {
                    $lecture = $topic->getLecture();
                    $imageSearch = null;

                    if ($lecture) {
                        // Extract the image search substring
                        if (preg_match('/\[Image Search: (.*?)\]/', $lecture, $matches)) {
                            $imageSearch = $matches[1];
                        }
                    }

                    return [
                        'id' => $topic->getId(),
                        'name' => $topic->getName(),
                        'subTopic' => $topic->getSubTopic(),
                        'subject' => $topic->getSubject() ? [
                            'id' => $topic->getSubject()->getId(),
                            'name' => $topic->getSubject()->getName()
                        ] : null,
                        'imageSearch' => $imageSearch,
                        'imageFileName' => $topic->getImageFileName(),
                        'recordingFileName' => $topic->getRecordingFileName(),
                        'createdAt' => $topic->getCreatedAt() ? $topic->getCreatedAt()->format('Y-m-d H:i:s') : null
                    ];
                }, $topics)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/image', name: 'update_topic_image', methods: ['PUT'])]
    public function updateImage(Request $request, int $id): JsonResponse
    {
        try {
            $topic = $this->topicService->getTopicById($id);
            if (!$topic) {
                return new JsonResponse(['error' => 'Topic not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $imageFileName = $data['imageFileName'] ?? null;

            if (!$imageFileName) {
                return new JsonResponse(['error' => 'Image file name is required'], Response::HTTP_BAD_REQUEST);
            }

            if ($this->topicService->updateTopicImage($topic, $imageFileName)) {
                return new JsonResponse([
                    'message' => 'Image updated successfully',
                    'topic' => [
                        'id' => $topic->getId(),
                        'imageFileName' => $topic->getImageFileName()
                    ]
                ]);
            }

            return new JsonResponse(['error' => 'Failed to update image'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/unposted/most-questions', name: 'get_unposted_topic_with_most_questions', methods: ['GET'])]
    public function getUnpostedTopicWithMostQuestions(Request $request): JsonResponse
    {
        try {
            $grade = $request->query->get('grade');
            $term = $request->query->get('term');

            if (!$grade || !$term) {
                return new JsonResponse([
                    'error' => 'Grade and term parameters are required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $topic = $this->topicService->getTopicWithMostQuestions((int) $grade, $term);

            if (!$topic) {
                return new JsonResponse([
                    'message' => 'No unposted topics found for the specified grade and term'
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'topic' => [
                    'id' => $topic->getId(),
                    'name' => $topic->getName(),
                    'subTopic' => $topic->getSubTopic(),
                    'subject' => $topic->getSubject() ? [
                        'id' => $topic->getSubject()->getId(),
                        'name' => $topic->getSubject()->getName()
                    ] : null,
                    'postedDate' => $topic->getPostedDate() ? $topic->getPostedDate()->format('Y-m-d H:i:s') : null
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/posted-date', name: 'update_topic_posted_date', methods: ['PUT'])]
    public function updatePostedDate(Request $request, int $id): JsonResponse
    {
        try {
            $topic = $this->topicService->getTopicById($id);
            if (!$topic) {
                return new JsonResponse(['error' => 'Topic not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $postedDate = isset($data['postedDate']) ? new \DateTime($data['postedDate']) : null;

            if ($this->topicService->updatePostedDate($topic, $postedDate)) {
                return new JsonResponse([
                    'message' => 'Posted date updated successfully',
                    'topic' => [
                        'id' => $topic->getId(),
                        'postedDate' => $topic->getPostedDate() ? $topic->getPostedDate()->format('Y-m-d H:i:s') : null
                    ]
                ]);
            }

            return new JsonResponse(['error' => 'Failed to update posted date'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}