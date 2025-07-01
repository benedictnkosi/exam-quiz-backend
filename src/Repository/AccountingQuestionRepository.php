<?php

namespace App\Repository;

use App\Entity\AccountingQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingQuestion>
 *
 * @method AccountingQuestion|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccountingQuestion|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccountingQuestion[]    findAll()
 * @method AccountingQuestion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountingQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingQuestion::class);
    }

    public function save(AccountingQuestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AccountingQuestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find questions by topic
     */
    public function findByTopic(string $topic): array
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->andWhere('at.subTopic = :topic')
            ->andWhere('aq.active = :active')
            ->setParameter('topic', $topic)
            ->setParameter('active', true)
            ->orderBy('aq.questionId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by level
     */
    public function findByLevel(string $level): array
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->andWhere('aq.level = :level')
            ->andWhere('aq.active = :active')
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->orderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.questionId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by topic and level
     */
    public function findByTopicAndLevel(string $topic, string $level): array
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->andWhere('at.subTopic = :topic')
            ->andWhere('aq.level = :level')
            ->andWhere('aq.active = :active')
            ->setParameter('topic', $topic)
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->orderBy('aq.questionId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->andWhere('aq.questionType = :type')
            ->andWhere('aq.active = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.questionId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all unique topics
     */
    public function findDistinctTopics(): array
    {
        $result = $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->select('DISTINCT at.subTopic')
            ->andWhere('aq.active = :active')
            ->setParameter('active', true)
            ->orderBy('at.subTopic', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'subTopic');
    }

    /**
     * Get all unique levels
     */
    public function findDistinctLevels(): array
    {
        $result = $this->createQueryBuilder('aq')
            ->select('DISTINCT aq.level')
            ->andWhere('aq.active = :active')
            ->setParameter('active', true)
            ->orderBy('aq.level', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'level');
    }

    /**
     * Get all unique question types
     */
    public function findDistinctQuestionTypes(): array
    {
        $result = $this->createQueryBuilder('aq')
            ->select('DISTINCT aq.questionType')
            ->andWhere('aq.active = :active')
            ->setParameter('active', true)
            ->orderBy('aq.questionType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'questionType');
    }

    /**
     * Get all unique main topics
     */
    public function findDistinctMainTopics(): array
    {
        $qb = $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->select('DISTINCT at.mainTopic')
            ->orderBy('at.mainTopic', 'ASC');

        $results = $qb->getQuery()->getScalarResult();
        return array_column($results, 'mainTopic');
    }

    /**
     * Find question by question ID
     */
    public function findByQuestionId(string $questionId): ?AccountingQuestion
    {
        return $this->createQueryBuilder('aq')
            ->andWhere('aq.questionId = :questionId')
            ->andWhere('aq.active = :active')
            ->setParameter('questionId', $questionId)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find questions by main topic
     */
    public function findByMainTopic(string $mainTopic): array
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->andWhere('at.mainTopic = :mainTopic')
            ->andWhere('aq.active = :active')
            ->setParameter('mainTopic', $mainTopic)
            ->setParameter('active', true)
            ->orderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.questionId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by main topic and level
     */
    public function findByMainTopicAndLevel(string $mainTopic, string $level): array
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->andWhere('at.mainTopic = :mainTopic')
            ->andWhere('aq.level = :level')
            ->andWhere('aq.active = :active')
            ->setParameter('mainTopic', $mainTopic)
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->orderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.questionId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subtopics (topics) by main topic with their levels
     */
    public function findSubtopicsByMainTopic(string $mainTopic): array
    {
        $qb = $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->select('DISTINCT at.subTopic, aq.level')
            ->where('at.mainTopic = :mainTopic')
            ->setParameter('mainTopic', $mainTopic)
            ->orderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.level', 'ASC');

        $results = $qb->getQuery()->getResult();
        
        // Group by topic and collect levels
        $grouped = [];
        foreach ($results as $result) {
            $topic = $result['subTopic'];
            $level = $result['level'];
            
            if (!isset($grouped[$topic])) {
                $grouped[$topic] = [
                    'topic' => $topic,
                    'levels' => []
                ];
            }
            
            if (!in_array($level, $grouped[$topic]['levels'])) {
                $grouped[$topic]['levels'][] = $level;
            }
        }
        
        return array_values($grouped);
    }

    /**
     * Count questions by topic and level
     */
    public function countByTopicAndLevel(string $topic, string $level): int
    {
        return $this->createQueryBuilder('aq')
            ->join('aq.accountingTopic', 'at')
            ->select('COUNT(aq.id)')
            ->andWhere('at.subTopic = :topic')
            ->andWhere('aq.level = :level')
            ->andWhere('aq.active = :active')
            ->setParameter('topic', $topic)
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
} 