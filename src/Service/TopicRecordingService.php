<?php

namespace App\Service;

use App\Entity\Subject;
use App\Entity\Topic;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;

class TopicRecordingService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findTopicsWithRecordings(string $uid, string $subjectName): array
    {
        $grade = 1; // Default grade
        if ($uid !== 'default') {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
            if (!$learner) {
                return [];
            }
            $grade = $learner->getGrade();
        }

        // First get the subjects for this grade
        $subjects = $this->entityManager->getRepository(Subject::class)
            ->createQueryBuilder('s')
            ->where('s.name LIKE :subjectName')
            ->andWhere('s.grade = :grade')
            ->setParameter('subjectName', $subjectName . '%')
            ->setParameter('grade', $grade)
            ->getQuery()
            ->getResult();

        if (empty($subjects)) {
            return [];
        }

        $subjectIds = array_map(function ($subject) {
            return $subject->getId();
        }, $subjects);

        // Then get topics with recordings for these subjects
        return $this->entityManager->getRepository(Topic::class)
            ->createQueryBuilder('t')
            ->where('t.subject IN (:subjectIds)')
            ->andWhere('t.recordingFileName IS NOT NULL')
            ->setParameter('subjectIds', $subjectIds)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecordingBySubTopic(string $subjectName, string $subTopic, ?int $grade = null): ?Topic
    {
        $qb = $this->entityManager->getRepository(Topic::class)
            ->createQueryBuilder('t')
            ->join('t.subject', 's')
            ->where('s.name LIKE :subjectName')
            ->andWhere('t.subTopic = :subTopic')
            ->andWhere('t.recordingFileName IS NOT NULL')
            ->setParameter('subjectName', $subjectName . '%')
            ->setParameter('subTopic', $subTopic);

        if ($grade !== null) {
            $qb->andWhere('s.grade = :grade')
                ->setParameter('grade', $grade);
        }

        $results = $qb->getQuery()->getResult();

        return $results[0] ?? null;
    }

    public function findRecordingByTopicId(int $topicId): ?Topic
    {
        return $this->entityManager->getRepository(Topic::class)
            ->createQueryBuilder('t')
            ->where('t.id = :topicId')
            ->andWhere('t.recordingFileName IS NOT NULL')
            ->setParameter('topicId', $topicId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}