<?php

namespace App\Repository;

use App\Entity\AccountingTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountingTopic>
 *
 * @method AccountingTopic|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccountingTopic|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccountingTopic[]    findAll()
 * @method AccountingTopic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountingTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountingTopic::class);
    }
} 