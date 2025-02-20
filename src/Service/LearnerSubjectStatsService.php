<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Subject;
use App\Entity\Result;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;

class LearnerSubjectStatsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getSubjectStats(string $learnerUid, string $subjectName): array
    {
        try {
            // Find the learner
            $learner = $this->entityManager->getRepository(Learner::class)
                ->findOneBy(['uid' => $learnerUid]);

            if (!$learner) {
                return [
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ];
            }

            // Find the subject
            $subject = $this->entityManager->getRepository(Subject::class)
                ->findOneBy(['name' => $subjectName, 'grade' => $learner->getGrade()]);

            if (!$subject) {
                return [
                    'status' => 'NOK',
                    'message' => 'Subject not found'
                ];
            }

            // Get answer statistics
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('
                    COUNT(r.id) as total_answers,
                    SUM(CASE WHEN r.outcome = :correct THEN 1 ELSE 0 END) as correct_answers,
                    SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) as incorrect_answers
                ')
                ->from(Result::class, 'r')
                ->join('r.question', 'q')
                ->where('r.learner = :learner')
                ->andWhere('q.subject = :subject')
                ->setParameters(new ArrayCollection([
                    new Parameter('learner', $learner),
                    new Parameter('subject', $subject),
                    new Parameter('correct', 'correct'),
                    new Parameter('incorrect', 'incorrect')
                ]));

            $result = $qb->getQuery()->getSingleResult();

            // Calculate percentages
            $totalAnswers = (int) $result['total_answers'];
            $correctAnswers = (int) $result['correct_answers'];
            $incorrectAnswers = (int) $result['incorrect_answers'];

            return [
                'status' => 'OK',
                'data' => [
                    'subject' => [
                        'id' => $subject->getId(),
                        'name' => $subject->getName()
                    ],
                    'stats' => [
                        'total_answers' => $totalAnswers,
                        'correct_answers' => $correctAnswers,
                        'incorrect_answers' => $incorrectAnswers,
                        'correct_percentage' => $totalAnswers > 0
                            ? round(($correctAnswers / $totalAnswers) * 100, 2)
                            : 0,
                        'incorrect_percentage' => $totalAnswers > 0
                            ? round(($incorrectAnswers / $totalAnswers) * 100, 2)
                            : 0
                    ]
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return [
                'status' => 'NOK',
                'message' => 'Error getting subject statistics: ' . $e->getMessage()
            ];
        }
    }
}