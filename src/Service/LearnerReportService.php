<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\Result;
use App\Entity\Subject;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Question;
use App\Repository\ResultRepository;

class LearnerReportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private readonly ResultRepository $resultRepository
    ) {
    }

    public function getSubjectPerformance(Learner $learner): array
    {
        // Get learner's terms and curriculum
        $learnerTerms = $learner->getTerms() ? array_map(function ($term) {
            return trim(str_replace('"', '', $term));
        }, explode(',', $learner->getTerms())) : [];

        $learnerCurriculum = $learner->getCurriculum() ? array_map(function ($curr) {
            return trim(str_replace('"', '', $curr));
        }, explode(',', $learner->getCurriculum())) : [];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select([
            's.name as subject_name',
            'COUNT(r.id) as total_answers',
            's.id as subject_id',
            'SUM(CASE WHEN r.outcome = :correct THEN 1 ELSE 0 END) as correct_answers',
            'SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) as incorrect_answers'
        ])
            ->from(Result::class, 'r')
            ->join('r.question', 'q')
            ->join('q.subject', 's')
            ->where('r.learner = :learner')
            ->andWhere('q.active = :active')
            ->andWhere('q.status = :status');

        // Add term condition if learner has terms specified
        if (!empty($learnerTerms)) {
            $qb->andWhere('q.term IN (:terms)');
        }

        // Add curriculum condition if learner has curriculum specified
        if (!empty($learnerCurriculum)) {
            $qb->andWhere('q.curriculum IN (:curriculum)');
        }

        $qb->groupBy('s.id')
            ->setParameter('learner', $learner)
            ->setParameter('correct', 'correct')
            ->setParameter('incorrect', 'incorrect')
            ->setParameter('active', true)
            ->setParameter('status', 'approved');

        if (!empty($learnerTerms)) {
            $qb->setParameter('terms', $learnerTerms);
        }

        if (!empty($learnerCurriculum)) {
            $qb->setParameter('curriculum', $learnerCurriculum);
        }

        $results = $qb->getQuery()->getResult();

        $report = [];
        foreach ($results as $result) {
            $total = $result['total_answers'];
            $correct = $result['correct_answers'];
            $percentage = $total > 0 ? ($correct / $total) * 100 : 0;

            $report[] = [
                'subject' => $result['subject_name'],
                'subjectId' => $result['subject_id'],
                'totalAnswers' => $total,
                'correctAnswers' => $correct,
                'incorrectAnswers' => $result['incorrect_answers'],
                'percentage' => round($percentage, 2),
                'grade' => $this->calculateGrade($percentage),
                'gradeDescription' => $this->getGradeDescription($percentage)
            ];
        }

        return $report;
    }

    public function getDailyActivity(Learner $learner, ?int $subjectId = null): array
    {
        $sevenDaysAgo = new \DateTime();
        $sevenDaysAgo->modify('-7 days');
        $sevenDaysAgo->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select([
            'SUBSTRING(r.created, 1, 10) as date',
            'COUNT(r.id) as count',
            'SUM(CASE WHEN r.outcome = :correct THEN 1 ELSE 0 END) as correct',
            'SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) as incorrect'
        ])
            ->from(Result::class, 'r')
            ->where('r.learner = :learner')
            ->andWhere('r.created >= :startDate')
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->setParameter('learner', $learner)
            ->setParameter('startDate', $sevenDaysAgo)
            ->setParameter('correct', 'correct')
            ->setParameter('incorrect', 'incorrect');

        if ($subjectId !== null) {
            $qb->join('r.question', 'q')
                ->join('q.subject', 's')
                ->andWhere('s.id = :subjectId')
                ->setParameter('subjectId', $subjectId);
        }

        $results = $qb->getQuery()->getResult();

        // Format the dates to be more readable
        $formattedResults = [];
        foreach ($results as $result) {
            $formattedResults[] = [
                'date' => $result['date'],
                'count' => (int) $result['count'],
                'correct' => (int) $result['correct'],
                'incorrect' => (int) $result['incorrect']
            ];
        }

        return $formattedResults;
    }

    public function getWeeklyProgress(Learner $learner, ?int $subjectId = null): array
    {
        $eightWeeksAgo = new \DateTime();
        $eightWeeksAgo->modify('-8 weeks');
        $eightWeeksAgo->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select([
            'SUBSTRING(r.created, 1, 4) as year',
            'SUBSTRING(r.created, 6, 2) as month',
            'SUBSTRING(r.created, 9, 2) as day',
            'MIN(SUBSTRING(r.created, 1, 10)) as first_date',
            'COUNT(r.id) as total_answers',
            'SUM(CASE WHEN r.outcome = :correct THEN 1 ELSE 0 END) as correct_answers',
            'SUM(CASE WHEN r.outcome = :incorrect THEN 1 ELSE 0 END) as incorrect_answers'
        ])
            ->from(Result::class, 'r')
            ->where('r.learner = :learner')
            ->andWhere('r.created >= :startDate')
            ->groupBy('year, month, day')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->addOrderBy('day', 'DESC')
            ->setParameter('learner', $learner)
            ->setParameter('startDate', $eightWeeksAgo)
            ->setParameter('correct', 'correct')
            ->setParameter('incorrect', 'incorrect');

        if ($subjectId !== null) {
            $qb->join('r.question', 'q')
                ->join('q.subject', 's')
                ->andWhere('s.id = :subjectId')
                ->setParameter('subjectId', $subjectId);
        }

        $results = $qb->getQuery()->getResult();

        // Group results by week in PHP
        $weeklyResults = [];
        foreach ($results as $result) {
            $date = new \DateTime($result['first_date']);
            $weekNumber = (int) $date->format('W');
            $year = $result['year'];
            $weekKey = $year . '-' . $weekNumber;

            // Calculate the Monday of this week
            $weekStart = clone $date;
            $weekStart->modify('monday this week');
            $weekStart->setTime(0, 0, 0);

            if (!isset($weeklyResults[$weekKey])) {
                $weeklyResults[$weekKey] = [
                    'week' => $weekKey,
                    'weekStart' => $weekStart->format('Y-m-d'),
                    'totalAnswers' => 0,
                    'correctAnswers' => 0,
                    'incorrectAnswers' => 0
                ];
            }

            $weeklyResults[$weekKey]['totalAnswers'] += (int) $result['total_answers'];
            $weeklyResults[$weekKey]['correctAnswers'] += (int) $result['correct_answers'];
            $weeklyResults[$weekKey]['incorrectAnswers'] += (int) $result['incorrect_answers'];
        }

        // Calculate percentages and grades for each week
        $report = [];
        foreach ($weeklyResults as $weekData) {
            $total = $weekData['totalAnswers'];
            $correct = $weekData['correctAnswers'];
            $percentage = $total > 0 ? ($correct / $total) * 100 : 0;

            $report[] = [
                'week' => $weekData['week'],
                'weekStart' => $weekData['weekStart'],
                'totalAnswers' => $total,
                'correctAnswers' => $correct,
                'incorrectAnswers' => $weekData['incorrectAnswers'],
                'percentage' => round($percentage, 2),
                'grade' => $this->calculateGrade($percentage),
                'gradeDescription' => $this->getGradeDescription($percentage)
            ];
        }

        // Sort by week in descending order
        usort($report, function ($a, $b) {
            return strcmp($b['week'], $a['week']);
        });

        return $report;
    }

    private function calculateGrade(float $percentage): int
    {
        if ($percentage >= 80)
            return 7;
        if ($percentage >= 70)
            return 6;
        if ($percentage >= 60)
            return 5;
        if ($percentage >= 50)
            return 4;
        if ($percentage >= 40)
            return 3;
        if ($percentage >= 30)
            return 2;
        return 1;
    }

    private function getGradeDescription(float $percentage): string
    {
        if ($percentage >= 80)
            return 'Outstanding achievement';
        if ($percentage >= 70)
            return 'Meritorious achievement';
        if ($percentage >= 60)
            return 'Substantial achievement';
        if ($percentage >= 50)
            return 'Adequate achievement';
        if ($percentage >= 40)
            return 'Moderate achievement';
        if ($percentage >= 30)
            return 'Elementary achievement';
        return 'Not achieved';
    }

    public function getLearnerReport(string $id, string $subjectName): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $id]);
        if (!$learner) {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['followMeCode' => $id]);

            if (!$learner) {
                throw new \Exception('Learner not found');
            }
        }

        $qb->select([
            'q.topic as subTopic',
            't.name as mainTopic',
            'COUNT(r.id) as total_attempts',
            'SUM(CASE WHEN r.outcome = \'correct\' THEN 1 ELSE 0 END) as correct_answers',
            'SUM(CASE WHEN r.outcome = \'incorrect\' THEN 1 ELSE 0 END) as incorrect_answers'
        ])
            ->from(Result::class, 'r')
            ->join('r.question', 'q')
            ->join('q.subject', 's')
            ->leftJoin('App\Entity\Topic', 't', 'WITH', 't.subTopic = q.topic AND t.subject = s')
            ->where('r.learner = :learner')
            ->andWhere('s.name like :subjectName')
            ->andWhere('q.topic IS NOT NULL')
            ->groupBy('t.name, q.topic')
            ->setParameter('learner', $learner)
            ->setParameter('subjectName', '%' . $subjectName . '%');

        $results = $qb->getQuery()->getResult();

        // Group results by main topic
        $groupedResults = [];
        foreach ($results as $row) {
            $mainTopic = $row['mainTopic'] ?? 'Uncategorized';
            $subTopic = $row['subTopic'];
            $totalAttempts = (int) $row['total_attempts'];
            $correctAnswers = (int) $row['correct_answers'];
            $incorrectAnswers = (int) $row['incorrect_answers'];
            $successRate = $totalAttempts > 0 ? round(($correctAnswers / $totalAttempts) * 100, 2) : 0;
            $grade = $this->calculateGrade($successRate);
            $gradeDescription = $this->getGradeDescription($successRate);

            if (!isset($groupedResults[$mainTopic])) {
                $groupedResults[$mainTopic] = [
                    'mainTopic' => $mainTopic,
                    'subTopics' => [],
                    'totalAttempts' => 0,
                    'correctAnswers' => 0,
                    'incorrectAnswers' => 0
                ];
            }

            $groupedResults[$mainTopic]['subTopics'][] = [
                'name' => $subTopic,
                'totalAttempts' => $totalAttempts,
                'correctAnswers' => $correctAnswers,
                'incorrectAnswers' => $incorrectAnswers,
                'successRate' => $successRate,
                'grade' => $grade,
                'gradeDescription' => $gradeDescription
            ];

            // Update main topic totals
            $groupedResults[$mainTopic]['totalAttempts'] += $totalAttempts;
            $groupedResults[$mainTopic]['correctAnswers'] += $correctAnswers;
            $groupedResults[$mainTopic]['incorrectAnswers'] += $incorrectAnswers;
        }

        // Calculate success rates and grades for main topics
        foreach ($groupedResults as &$mainTopic) {
            $mainTopic['successRate'] = $mainTopic['totalAttempts'] > 0
                ? round(($mainTopic['correctAnswers'] / $mainTopic['totalAttempts']) * 100, 2)
                : 0;
            $mainTopic['grade'] = $this->calculateGrade($mainTopic['successRate']);
            $mainTopic['gradeDescription'] = $this->getGradeDescription($mainTopic['successRate']);
        }

        // Convert to indexed array and sort by main topic name
        $groupedResults = array_values($groupedResults);
        usort($groupedResults, function ($a, $b) {
            return strcmp($a['mainTopic'], $b['mainTopic']);
        });

        return $groupedResults;
    }
}