<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LearnerTimetableService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function updateTimetable(string $learnerUid, array $timetable): Learner
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);

        if (!$learner) {
            throw new NotFoundHttpException('Learner not found');
        }

        $learner->setTimetable($timetable);
        $this->entityManager->flush();

        return $learner;
    }
}