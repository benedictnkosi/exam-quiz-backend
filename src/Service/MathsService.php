<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Learner;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;

class MathsService
{
    public function __construct(
        private readonly QuestionRepository $questionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get all unique topics from questions where steps is not null and subject grade matches learner grade
     * 
     * @param string $learnerUid The learner's UID
     * @return array Array of unique topics
     */
    public function getTopicsWithSteps(string $learnerUid, string $subjectName): array
    {
        // Get the learner
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return [];
        }

        $grade = $learner->getGrade();
        if (!$grade) {
            return [];
        }

        // Create query to get unique topics
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT q.topic')
            ->from(Question::class, 'q')
            ->join('q.subject', 's')
            ->where('q.steps IS NOT NULL')
            ->andWhere('s.grade = :grade')
            ->andWhere('q.topic IS NOT NULL')
            ->andWhere('s.name LIKE :subjectName')
            ->setParameter('grade', $grade)
            ->setParameter('subjectName', $subjectName . '%')
            ->orderBy('q.topic', 'ASC');

        $result = $qb->getQuery()->getResult();

        return array_column($result, 'topic');
    }

    /**
     * Get question IDs with steps for a specific topic and grade
     * 
     * @param string $topic The topic to filter by
     * @param int $grade The grade number to filter by
     * @return array Array of question IDs
     */
    public function getQuestionIdsWithSteps(string $topic, int $grade, string $subjectName): array
    {
        // Create query to get question IDs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('q.id')
            ->from(Question::class, 'q')
            ->join('q.subject', 's')
            ->join('s.grade', 'g')
            ->where('q.steps IS NOT NULL')
            ->andWhere('q.topic = :topic')
            ->andWhere('g.number = :grade')
            ->andWhere('q.active = :active')
            ->andWhere('s.name LIKE :subjectName')
            ->setParameter('topic', $topic)
            ->setParameter('grade', $grade)
            ->setParameter('active', true)
            ->setParameter('subjectName', $subjectName . '%')
            ->orderBy('q.id', 'ASC');

        $result = $qb->getQuery()->getResult();

        return array_column($result, 'id');
    }
}