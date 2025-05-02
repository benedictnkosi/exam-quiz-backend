<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;

class LearnerProfileService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function updatePublicProfile(Learner $learner, bool $isPublic): Learner
    {
        $learner->setPublicProfile($isPublic);
        $this->entityManager->persist($learner);
        $this->entityManager->flush();

        return $learner;
    }
}