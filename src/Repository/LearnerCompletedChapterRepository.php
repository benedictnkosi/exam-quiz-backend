<?php

namespace App\Repository;

use App\Entity\LearnerCompletedChapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LearnerCompletedChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerCompletedChapter::class);
    }

    public function findByLearnerUid(string $learnerUid): array
    {
        return $this->findBy(['learnerUid' => $learnerUid], ['completedAt' => 'DESC']);
    }

    public function findByLearnerUidAndChapterName(string $learnerUid, string $chapterName): ?LearnerCompletedChapter
    {
        return $this->findOneBy([
            'learnerUid' => $learnerUid,
            'chapterName' => $chapterName
        ]);
    }

    public function findByLearnerUidAndBookTitle(string $learnerUid, string $bookTitle): array
    {
        return $this->findBy([
            'learnerUid' => $learnerUid,
            'bookTitle' => $bookTitle
        ], ['completedAt' => 'DESC']);
    }

    public function countByLearnerUid(string $learnerUid): int
    {
        return $this->count(['learnerUid' => $learnerUid]);
    }
} 