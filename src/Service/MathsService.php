<?php

namespace App\Service;

use App\Dto\TopicHierarchyDto;
use App\Entity\Question;
use App\Repository\MathLessonRepository;
use App\Repository\QuestionRepository;
use Doctrine\ORM\Query\Expr\OrderBy;

class MathsService
{
    public function __construct(
        private readonly MathLessonRepository $mathLessonRepository,
        private readonly QuestionRepository $questionRepository
    ) {
    }

    /**
     * Get all topics with their subtopics organized in a hierarchical structure
     * 
     * @param int|null $grade Filter topics by grade level
     * @return TopicHierarchyDto[]
     */
    public function getTopicHierarchy(?int $grade = null): array
    {
        $criteria = [];
        if ($grade !== null) {
            $criteria['grade'] = $grade;
        }

        $lessons = $this->mathLessonRepository->findBy($criteria, ['topic' => 'ASC', 'subTopic' => 'ASC']);

        $topicHierarchy = [];

        foreach ($lessons as $lesson) {
            $topic = $lesson->getTopic();
            $subTopic = $lesson->getSubTopic();

            if (!isset($topicHierarchy[$topic])) {
                $topicHierarchy[$topic] = new TopicHierarchyDto($topic);
            }

            $topicHierarchy[$topic]->addSubTopic($subTopic);
        }

        return array_values($topicHierarchy);
    }

    /**
     * Get all unique topics
     * 
     * @param int|null $grade Filter topics by grade level
     * @return string[]
     */
    public function getAllTopics(?int $grade = null): array
    {
        $qb = $this->mathLessonRepository->createQueryBuilder('ml')
            ->select('DISTINCT ml.topic')
            ->orderBy('ml.topic', 'ASC');

        if ($grade !== null) {
            $qb->where('ml.grade = :grade')
                ->setParameter('grade', $grade);
        }

        $result = $qb->getQuery()->getResult();

        return array_column($result, 'topic');
    }

    /**
     * Get all subtopics for a specific topic
     * 
     * @param string $topic
     * @param int|null $grade Filter subtopics by grade level
     * @return string[]
     */
    public function getSubTopicsForTopic(string $topic, ?int $grade = null): array
    {
        $qb = $this->mathLessonRepository->createQueryBuilder('ml')
            ->select('DISTINCT ml.subTopic')
            ->where('ml.topic = :topic')
            ->setParameter('topic', $topic)
            ->orderBy('ml.subTopic', 'ASC');

        if ($grade !== null) {
            $qb->andWhere('ml.grade = :grade')
                ->setParameter('grade', $grade);
        }

        $result = $qb->getQuery()->getResult();

        return array_column($result, 'subTopic');
    }

    /**
     * Get lessons filtered by subtopic and grade
     * 
     * @param string $subTopic
     * @param int $grade
     * @return array
     */
    public function getLessonsByFilters(string $subTopic, int $grade): array
    {
        $lessons = $this->mathLessonRepository->findBy(
            [
                'subTopic' => $subTopic,
                'grade' => $grade
            ],
            ['id' => 'ASC']
        );

        // Ensure questions are loaded
        foreach ($lessons as $lesson) {
            if ($lesson->getQuestion() === null) {
                continue;
            }
            $this->mathLessonRepository->getEntityManager()->refresh($lesson->getQuestion());
        }

        return $lessons;
    }
}