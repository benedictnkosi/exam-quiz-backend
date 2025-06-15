<?php

namespace App\Service;

use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;

class LearnerService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function incrementLanguagePoints(Learner $learner, int $points): void
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Points must be a positive number');
        }

        $currentPoints = $learner->getLanguagePoints();
        $learner->setLanguagePoints($currentPoints + $points);

        $this->em->persist($learner);
        $this->em->flush();
    }
}