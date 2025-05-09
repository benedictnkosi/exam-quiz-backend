<?php

namespace App\Repository;

use App\Entity\PushNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushNotification>
 *
 * @method PushNotification|null find($id, $lockMode = null, $lockVersion = null)
 * @method PushNotification|null findOneBy(array $criteria, array $orderBy = null)
 * @method PushNotification[]    findAll()
 * @method PushNotification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PushNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushNotification::class);
    }

    public function save(PushNotification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PushNotification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}