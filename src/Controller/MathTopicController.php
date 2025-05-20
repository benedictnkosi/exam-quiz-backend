<?php

namespace App\Controller;

use App\Service\LearnerService;
use App\Service\MathsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/math-topics')]
class MathTopicController extends AbstractController
{
    public function __construct(
        private readonly MathsService $mathsService,
        private readonly LearnerService $learnerService
    ) {
    }

    #[Route('', name: 'api_math_topics_list', methods: ['GET'])]
    #[OA\Tag(name: 'Math Topics')]
    #[OA\Parameter(
        name: 'learnerUid',
        description: 'The UID of the learner to filter topics by their grade',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of all topics with their subtopics for the learner\'s grade',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'topic', type: 'string'),
                    new OA\Property(
                        property: 'subTopics',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Learner not found'
    )]
    public function getTopicHierarchy(string $learnerUid): JsonResponse
    {
        $grade = $this->learnerService->getLearnerGrade($learnerUid);
        $hierarchy = $this->mathsService->getTopicHierarchy($grade);
        return $this->json($hierarchy);
    }

    #[Route('/topics', name: 'api_math_topics_simple_list', methods: ['GET'])]
    #[OA\Tag(name: 'Math Topics')]
    #[OA\Parameter(
        name: 'learnerUid',
        description: 'The UID of the learner to filter topics by their grade',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of all unique topics for the learner\'s grade',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'string')
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Learner not found'
    )]
    public function getAllTopics(string $learnerUid): JsonResponse
    {
        $grade = $this->learnerService->getLearnerGrade($learnerUid);
        $topics = $this->mathsService->getAllTopics($grade);
        return $this->json($topics);
    }

    #[Route('/topics/{topic}/subtopics', name: 'api_math_topics_subtopics', methods: ['GET'])]
    #[OA\Tag(name: 'Math Topics')]
    #[OA\Parameter(
        name: 'topic',
        description: 'The topic to get subtopics for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'learnerUid',
        description: 'The UID of the learner to filter subtopics by their grade',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of subtopics for the given topic and learner\'s grade',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'string')
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Topic or learner not found'
    )]
    public function getSubTopicsForTopic(string $topic, string $learnerUid): JsonResponse
    {
        $grade = $this->learnerService->getLearnerGrade($learnerUid);
        $subTopics = $this->mathsService->getSubTopicsForTopic($topic, $grade);

        if (empty($subTopics)) {
            return $this->json(['error' => 'Topic not found'], 404);
        }

        return $this->json($subTopics);
    }

    #[Route('/lessons', name: 'api_math_lessons_by_filters', methods: ['GET'])]
    #[OA\Tag(name: 'Math Topics')]
    #[OA\Parameter(
        name: 'subTopic',
        description: 'The subtopic to filter lessons by',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'learnerUid',
        description: 'The UID of the learner to filter lessons by their grade',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the list of math lessons matching the filters for the learner\'s grade',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(property: 'grade', type: 'integer'),
                    new OA\Property(property: 'topic', type: 'string'),
                    new OA\Property(property: 'subTopic', type: 'string'),
                    new OA\Property(
                        property: 'question',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'text', type: 'string'),
                            new OA\Property(property: 'type', type: 'string'),
                            new OA\Property(property: 'difficulty', type: 'integer'),
                            new OA\Property(property: 'points', type: 'integer')
                        ]
                    ),
                    new OA\Property(property: 'questionId', type: 'integer'),
                    new OA\Property(
                        property: 'steps',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'step_number', type: 'integer'),
                                new OA\Property(property: 'type', type: 'string', nullable: true),
                                new OA\Property(property: 'prompt', type: 'string'),
                                new OA\Property(property: 'expression', type: 'string'),
                                new OA\Property(
                                    property: 'options',
                                    type: 'array',
                                    items: new OA\Items(type: 'string')
                                ),
                                new OA\Property(property: 'answer', type: 'string'),
                                new OA\Property(property: 'hint', type: 'string'),
                                new OA\Property(property: 'teach', type: 'string'),
                                new OA\Property(property: 'final_expression', type: 'string')
                            ]
                        )
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'No lessons found for the given filters or learner not found'
    )]
    public function getLessonsByFilters(string $subTopic, string $learnerUid): JsonResponse
    {
        $grade = $this->learnerService->getLearnerGrade($learnerUid);
        $lessons = $this->mathsService->getLessonsByFilters($subTopic, $grade);

        if (empty($lessons)) {
            return $this->json(['error' => 'No lessons found for the given filters'], 404);
        }

        return $this->json($lessons);
    }
}