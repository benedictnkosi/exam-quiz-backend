<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class LeaderboardService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getTopStudents(int $limit = 10, ?string $uid = null): array
    {
        // Get top students ordered by points
        $topStudents = $this->entityManager->createQueryBuilder()
            ->select('l.uid, l.name, l.points, l.avatar')
            ->from(Learner::class, 'l')
            ->orderBy('l.points', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [
            'topStudents' => $topStudents,
            'currentUserPosition' => null
        ];

        // If UID is provided, get the current user's position
        if ($uid) {
            $rsm = new ResultSetMapping();
            $rsm->addScalarResult('position', 'position');

            $sql = "
                SELECT position 
                FROM (
                    SELECT uid, ROW_NUMBER() OVER (ORDER BY points DESC) as position 
                    FROM learner
                ) ranked 
                WHERE uid = :uid
            ";

            $query = $this->entityManager->createNativeQuery($sql, $rsm);
            $query->setParameter('uid', $uid);
            
            $position = $query->getSingleScalarResult();
            $result['currentUserPosition'] = (int) $position;
        }

        return $result;
    }
} 