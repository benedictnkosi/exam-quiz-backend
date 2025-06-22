<?php

namespace App\Repository;

use App\Entity\Topic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Topic>
 *
 * @method Topic|null find($id, $lockMode = null, $lockVersion = null)
 * @method Topic|null findOneBy(array $criteria, array $orderBy = null)
 * @method Topic[]    findAll()
 * @method Topic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }

    public function findOneBySubTopicAndSubject(?string $subTopic, ?int $subjectId): ?Topic
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.subTopic = :subTopic')
            ->andWhere('t.subject = :subjectId')
            ->setParameter('subTopic', $subTopic)
            ->setParameter('subjectId', $subjectId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Topic $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 