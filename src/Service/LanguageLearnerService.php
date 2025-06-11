<?php

namespace App\Service;

use App\Entity\LanguageLearner;
use Doctrine\ORM\EntityManagerInterface;

class LanguageLearnerService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function incrementPoints(LanguageLearner $learner, int $points): void
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Points must be a positive number');
        }

        $currentPoints = $learner->getPoints();
        $learner->setPoints($currentPoints + $points);

        $this->em->persist($learner);
        $this->em->flush();
    }
}