<?php

namespace App\Service;

use App\Entity\Subject;
use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;

class TopicRecordingService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findTopicsWithRecordings(string $subjectName): array
    {
        return $this->entityManager->getRepository(Topic::class)
            ->createQueryBuilder('t')
            ->join('t.subject', 's')
            ->where('s.name LIKE :subjectName')
            ->andWhere('t.recordingFileName IS NOT NULL')
            ->setParameter('subjectName', $subjectName . '%')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecordingBySubTopic(string $subjectName, string $subTopic): ?Topic
    {
        return $this->entityManager->getRepository(Topic::class)
            ->createQueryBuilder('t')
            ->join('t.subject', 's')
            ->where('s.name LIKE :subjectName')
            ->andWhere('t.subTopic = :subTopic')
            ->andWhere('t.recordingFileName IS NOT NULL')
            ->setParameter('subjectName', $subjectName . '%')
            ->setParameter('subTopic', $subTopic)
            ->getQuery()
            ->getOneOrNullResult();
    }
}