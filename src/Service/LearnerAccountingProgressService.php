<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\AccountingQuestion;
use App\Entity\AccountingTopic;
use App\Entity\LearnerAccountingProgress;
use App\Repository\LearnerAccountingProgressRepository;
use Doctrine\ORM\EntityManagerInterface;

class LearnerAccountingProgressService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LearnerAccountingProgressRepository $learnerAccountingProgressRepository
    ) {
    }

    /**
     * Add a completed question for a learner
     */
    public function addCompletedQuestion(
        Learner $learner,
        AccountingQuestion $question,
        bool $isCorrect,
        ?string $learnerAnswer = null,
        ?string $correctAnswer = null,
        ?int $timeSpent = null,
        ?int $attempts = null,
        ?string $notes = null
    ): LearnerAccountingProgress {
        // Check if this learner has already completed this question
        $existingProgress = $this->learnerAccountingProgressRepository->findByLearnerAndQuestion(
            $learner->getId(),
            $question->getId()
        );

        if ($existingProgress) {
            // Update existing progress
            $existingProgress->setIsCorrect($isCorrect);
            $existingProgress->setLearnerAnswer($learnerAnswer);
            $existingProgress->setCorrectAnswer($correctAnswer);
            $existingProgress->setTimeSpent($timeSpent);
            $existingProgress->setAttempts($attempts);
            $existingProgress->setNotes($notes);
            $existingProgress->setCompletedAt(new \DateTime());

            $this->entityManager->flush();
            return $existingProgress;
        }

        // Create new progress record
        $progress = new LearnerAccountingProgress();
        $progress->setLearner($learner);
        $progress->setAccountingQuestion($question);
        $progress->setAccountingTopic($question->getAccountingTopic());
        $progress->setIsCorrect($isCorrect);
        $progress->setLearnerAnswer($learnerAnswer);
        $progress->setCorrectAnswer($correctAnswer);
        $progress->setTimeSpent($timeSpent);
        $progress->setAttempts($attempts);
        $progress->setNotes($notes);

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        return $progress;
    }

    /**
     * Get all completed topics for a learner (topics where ALL questions are completed)
     */
    public function getCompletedTopics(Learner $learner): array
    {
        $completedTopics = $this->learnerAccountingProgressRepository->findCompletedTopicsByLearner($learner->getId());
        
        // Group by main topic
        $groupedTopics = [];
        foreach ($completedTopics as $topic) {
            $mainTopic = $topic['mainTopic'];
            if (!isset($groupedTopics[$mainTopic])) {
                $groupedTopics[$mainTopic] = [
                    'mainTopic' => $mainTopic,
                    'subtopics' => []
                ];
            }
            $groupedTopics[$mainTopic]['subtopics'][] = [
                'id' => $topic['id'],
                'subTopic' => $topic['subTopic'],
                'completedQuestions' => $topic['completedQuestions']
            ];
        }

        return array_values($groupedTopics);
    }

    /**
     * Get topic progress for a learner (showing completion percentage for each topic)
     */
    public function getTopicProgress(Learner $learner): array
    {
        $topicProgress = $this->learnerAccountingProgressRepository->findTopicProgressByLearner($learner->getId());
        
        // Group by main topic
        $groupedProgress = [];
        foreach ($topicProgress as $progress) {
            $mainTopic = $progress['mainTopic'];
            if (!isset($groupedProgress[$mainTopic])) {
                $groupedProgress[$mainTopic] = [
                    'mainTopic' => $mainTopic,
                    'subtopics' => []
                ];
            }
            $groupedProgress[$mainTopic]['subtopics'][] = [
                'id' => $progress['id'],
                'subTopic' => $progress['subTopic'],
                'completedQuestions' => $progress['completedQuestions'],
                'totalQuestions' => $progress['totalQuestions'],
                'completionPercentage' => $progress['completionPercentage'],
                'isCompleted' => $progress['isCompleted']
            ];
        }

        return array_values($groupedProgress);
    }

    /**
     * Get progress statistics for a learner
     */
    public function getProgressStats(Learner $learner): array
    {
        return $this->learnerAccountingProgressRepository->findProgressStatsByLearner($learner->getId());
    }

    /**
     * Get recent progress for a learner
     */
    public function getRecentProgress(Learner $learner, int $limit = 10): array
    {
        return $this->learnerAccountingProgressRepository->findRecentProgressByLearner($learner->getId(), $limit);
    }

    /**
     * Get progress by topic for a learner
     */
    public function getProgressByTopic(Learner $learner, int $topicId): array
    {
        return $this->learnerAccountingProgressRepository->findByLearnerAndTopic($learner->getId(), $topicId);
    }

    /**
     * Get all progress for a learner
     */
    public function getAllProgress(Learner $learner): array
    {
        return $this->learnerAccountingProgressRepository->findByLearner($learner->getId());
    }

    /**
     * Get progress by date range for a learner
     */
    public function getProgressByDateRange(Learner $learner, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->learnerAccountingProgressRepository->findByLearnerAndDateRange(
            $learner->getId(),
            $startDate,
            $endDate
        );
    }

    /**
     * Check if a learner has completed a specific question
     */
    public function hasCompletedQuestion(Learner $learner, int $questionId): bool
    {
        $progress = $this->learnerAccountingProgressRepository->findByLearnerAndQuestion(
            $learner->getId(),
            $questionId
        );
        
        return $progress !== null;
    }

    /**
     * Get completion status for a topic
     */
    public function getTopicCompletionStatus(Learner $learner, int $topicId): array
    {
        $progress = $this->getProgressByTopic($learner, $topicId);
        
        $totalQuestions = count($progress);
        $correctAnswers = count(array_filter($progress, fn($p) => $p->isCorrect()));
        $accuracy = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
        
        return [
            'topicId' => $topicId,
            'totalQuestions' => $totalQuestions,
            'correctAnswers' => $correctAnswers,
            'accuracy' => $accuracy,
            'lastCompleted' => $totalQuestions > 0 ? $progress[0]->getCompletedAt() : null
        ];
    }

    /**
     * Get learner's progress summary
     */
    public function getProgressSummary(Learner $learner): array
    {
        $stats = $this->getProgressStats($learner);
        $completedTopics = $this->getCompletedTopics($learner);
        $recentProgress = $this->getRecentProgress($learner, 5);

        return [
            'stats' => $stats,
            'completedTopics' => $completedTopics,
            'recentProgress' => array_map(function($progress) {
                return [
                    'questionId' => $progress->getAccountingQuestion()->getQuestionId(),
                    'topic' => $progress->getAccountingTopic()->getSubTopic(),
                    'mainTopic' => $progress->getAccountingTopic()->getMainTopic(),
                    'isCorrect' => $progress->isCorrect(),
                    'completedAt' => $progress->getCompletedAt(),
                    'timeSpent' => $progress->getTimeSpent()
                ];
            }, $recentProgress)
        ];
    }

    /**
     * Get topic summary with statistics for each level
     */
    public function getTopicSummary(Learner $learner): array
    {
        $topicSummary = $this->learnerAccountingProgressRepository->findTopicSummaryByLearner($learner->getId());
        
        // Group by main topic
        $groupedSummary = [];
        foreach ($topicSummary as $summary) {
            $mainTopic = $summary['mainTopic'];
            if (!isset($groupedSummary[$mainTopic])) {
                $groupedSummary[$mainTopic] = [
                    'mainTopic' => $mainTopic,
                    'subtopics' => []
                ];
            }
            $groupedSummary[$mainTopic]['subtopics'][] = [
                'topicId' => $summary['topicId'],
                'subTopic' => $summary['subTopic'],
                'levels' => $summary['levels']
            ];
        }

        return array_values($groupedSummary);
    }
} 