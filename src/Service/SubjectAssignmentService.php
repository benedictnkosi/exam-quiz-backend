<?php

namespace App\Service;

use App\Entity\Subject;
use App\Entity\Learner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubjectAssignmentService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Assign a subject to a learner
     *
     * @param int $subjectId The ID of the subject
     * @param string $uid The UID of the learner
     * @return Subject The updated subject
     * @throws NotFoundHttpException if subject or learner is not found
     */
    public function assignSubjectToLearner(int $subjectId, string $uid): Subject
    {
        $subject = $this->entityManager->getRepository(Subject::class)->find($subjectId);
        if (!$subject) {
            throw new NotFoundHttpException('Subject not found');
        }

        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            throw new NotFoundHttpException('Learner not found');
        }

        $subject->setCapturer($learner);
        $this->entityManager->persist($subject);
        $this->entityManager->flush();

        return $subject;
    }
}