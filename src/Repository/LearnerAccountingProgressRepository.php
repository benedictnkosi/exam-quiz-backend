<?php

namespace App\Repository;

use App\Entity\LearnerAccountingProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LearnerAccountingProgress>
 *
 * @method LearnerAccountingProgress|null find($id, $lockMode = null, $lockVersion = null)
 * @method LearnerAccountingProgress|null findOneBy(array $criteria, array $orderBy = null)
 * @method LearnerAccountingProgress[]    findAll()
 * @method LearnerAccountingProgress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LearnerAccountingProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearnerAccountingProgress::class);
    }

    /**
     * Find progress by learner and question
     */
    public function findByLearnerAndQuestion(int $learnerId, int $questionId): ?LearnerAccountingProgress
    {
        return $this->createQueryBuilder('lap')
            ->andWhere('lap.learner = :learnerId')
            ->andWhere('lap.accountingQuestion = :questionId')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all progress for a learner
     */
    public function findByLearner(int $learnerId): array
    {
        return $this->createQueryBuilder('lap')
            ->join('lap.accountingQuestion', 'aq')
            ->join('lap.accountingTopic', 'at')
            ->andWhere('lap.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->orderBy('lap.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find progress by learner and topic
     */
    public function findByLearnerAndTopic(int $learnerId, int $topicId): array
    {
        return $this->createQueryBuilder('lap')
            ->join('lap.accountingQuestion', 'aq')
            ->andWhere('lap.learner = :learnerId')
            ->andWhere('lap.accountingTopic = :topicId')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('topicId', $topicId)
            ->orderBy('lap.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed topics for a learner (topics where ALL questions are completed)
     */
    public function findCompletedTopicsByLearner(int $learnerId): array
    {
        // Get topics where learner has completed all questions
        $completedTopics = $this->createQueryBuilder('lap')
            ->select('at.id, at.mainTopic, at.subTopic, COUNT(lap.id) as completedQuestions')
            ->join('lap.accountingTopic', 'at')
            ->andWhere('lap.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->groupBy('at.id, at.mainTopic, at.subTopic')
            ->having('COUNT(lap.id) = (SELECT COUNT(aq2.id) FROM App\Entity\AccountingQuestion aq2 WHERE aq2.accountingTopic = at.id AND aq2.active = true)')
            ->orderBy('at.mainTopic', 'ASC')
            ->addOrderBy('at.subTopic', 'ASC')
            ->getQuery()
            ->getResult();

        return $completedTopics;
    }

    /**
     * Find progress statistics for a learner
     */
    public function findProgressStatsByLearner(int $learnerId): array
    {
        $qb = $this->createQueryBuilder('lap')
            ->select('
                COUNT(lap.id) as totalQuestions,
                SUM(CASE WHEN lap.isCorrect = true THEN 1 ELSE 0 END) as correctAnswers,
                AVG(lap.timeSpent) as avgTimeSpent,
                COUNT(DISTINCT lap.accountingTopic) as topicsCompleted
            ')
            ->andWhere('lap.learner = :learnerId')
            ->setParameter('learnerId', $learnerId);

        $result = $qb->getQuery()->getSingleResult();

        $totalQuestions = (int) $result['totalQuestions'];
        $correctAnswers = (int) $result['correctAnswers'];
        $accuracy = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;

        return [
            'totalQuestions' => $totalQuestions,
            'correctAnswers' => $correctAnswers,
            'accuracy' => $accuracy,
            'avgTimeSpent' => $result['avgTimeSpent'] ? round($result['avgTimeSpent'], 2) : null,
            'topicsCompleted' => (int) $result['topicsCompleted']
        ];
    }

    /**
     * Find recent progress for a learner
     */
    public function findRecentProgressByLearner(int $learnerId, int $limit = 10): array
    {
        return $this->createQueryBuilder('lap')
            ->join('lap.accountingQuestion', 'aq')
            ->join('lap.accountingTopic', 'at')
            ->andWhere('lap.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->orderBy('lap.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find progress by date range for a learner
     */
    public function findByLearnerAndDateRange(int $learnerId, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('lap')
            ->join('lap.accountingQuestion', 'aq')
            ->join('lap.accountingTopic', 'at')
            ->andWhere('lap.learner = :learnerId')
            ->andWhere('lap.completedAt >= :startDate')
            ->andWhere('lap.completedAt <= :endDate')
            ->setParameter('learnerId', $learnerId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('lap.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find topic progress for a learner (showing completion percentage for each topic)
     */
    public function findTopicProgressByLearner(int $learnerId): array
    {
        $qb = $this->createQueryBuilder('lap')
            ->select('
                at.id,
                at.mainTopic,
                at.subTopic,
                COUNT(lap.id) as completedQuestions,
                (SELECT COUNT(aq2.id) FROM App\Entity\AccountingQuestion aq2 WHERE aq2.accountingTopic = at.id AND aq2.active = true) as totalQuestions
            ')
            ->join('lap.accountingTopic', 'at')
            ->andWhere('lap.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->groupBy('at.id, at.mainTopic, at.subTopic')
            ->orderBy('at.mainTopic', 'ASC')
            ->addOrderBy('at.subTopic', 'ASC');

        $results = $qb->getQuery()->getResult();
        
        // Calculate completion percentage for each topic
        foreach ($results as &$result) {
            $completed = (int) $result['completedQuestions'];
            $total = (int) $result['totalQuestions'];
            $result['completionPercentage'] = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
            $result['isCompleted'] = $completed >= $total;
        }

        return $results;
    }

    /**
     * Find topic summary with statistics for each level
     */
    public function findTopicSummaryByLearner(int $learnerId): array
    {
        $qb = $this->createQueryBuilder('lap')
            ->select('
                at.id,
                at.mainTopic,
                at.subTopic,
                aq.level,
                COUNT(lap.id) as questionsDone,
                (SELECT COUNT(aq2.id) FROM App\Entity\AccountingQuestion aq2 WHERE aq2.accountingTopic = at.id AND aq2.level = aq.level AND aq2.active = true) as totalQuestions,
                SUM(CASE WHEN lap.isCorrect = true THEN 1 ELSE 0 END) as correctAnswers,
                AVG(lap.timeSpent) as avgTimeSpent
            ')
            ->join('lap.accountingTopic', 'at')
            ->join('lap.accountingQuestion', 'aq')
            ->andWhere('lap.learner = :learnerId')
            ->setParameter('learnerId', $learnerId)
            ->groupBy('at.id, at.mainTopic, at.subTopic, aq.level')
            ->orderBy('at.mainTopic', 'ASC')
            ->addOrderBy('at.subTopic', 'ASC')
            ->addOrderBy('aq.level', 'ASC');

        $results = $qb->getQuery()->getResult();
        
        // Calculate accuracy percentage and organize by topic
        $topicSummary = [];
        foreach ($results as $result) {
            $topicKey = $result['mainTopic'] . '|' . $result['subTopic'];
            
            if (!isset($topicSummary[$topicKey])) {
                $topicSummary[$topicKey] = [
                    'mainTopic' => $result['mainTopic'],
                    'subTopic' => $result['subTopic'],
                    'topicId' => $result['id'],
                    'levels' => []
                ];
            }
            
            $questionsDone = (int) $result['questionsDone'];
            $totalQuestions = (int) $result['totalQuestions'];
            $correctAnswers = (int) $result['correctAnswers'];
            $accuracy = $questionsDone > 0 ? round(($correctAnswers / $questionsDone) * 100, 2) : 0;
            $completionPercentage = $totalQuestions > 0 ? round(($questionsDone / $totalQuestions) * 100, 2) : 0;
            
            $topicSummary[$topicKey]['levels'][] = [
                'level' => $result['level'],
                'questionsDone' => $questionsDone,
                'totalQuestions' => $totalQuestions,
                'correctAnswers' => $correctAnswers,
                'accuracy' => $accuracy,
                'completionPercentage' => $completionPercentage,
                'avgTimeSpent' => $result['avgTimeSpent'] ? round($result['avgTimeSpent'], 2) : null,
                'isCompleted' => $questionsDone >= $totalQuestions
            ];
        }

        return array_values($topicSummary);
    }
} 