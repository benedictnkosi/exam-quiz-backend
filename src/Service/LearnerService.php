<?php

namespace App\Service;

use App\Repository\LearnerRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LearnerService
{
    public function __construct(
        private readonly LearnerRepository $learnerRepository
    ) {
    }

    /**
     * Get learner's grade by UID
     * 
     * @param string $uid
     * @return int
     * @throws NotFoundHttpException if learner not found
     */
    public function getLearnerGrade(string $uid): int
    {
        $learner = $this->learnerRepository->findOneBy(['uid' => $uid]);

        if (!$learner) {
            throw new NotFoundHttpException('Learner not found');
        }

        return $learner->getGrade();
    }
}