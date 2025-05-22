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

        $grade = $learner->getGrade();
        if (!$grade) {
            throw new NotFoundHttpException('Learner grade not found');
        }

        return $grade->getNumber();
    }

    /**
     * Update learner's terms
     * 
     * @param string $uid
     * @param string $terms
     * @return void
     * @throws NotFoundHttpException if learner not found
     */
    public function updateLearnerTerms(string $uid, string $terms): void
    {
        $learner = $this->learnerRepository->findOneBy(['uid' => $uid]);

        if (!$learner) {
            throw new NotFoundHttpException('Learner not found');
        }

        $learner->setTerms($terms);
        $this->learnerRepository->save($learner, true);
    }
}