<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class QuestionTopicCountService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get the percentage of questions per topic for a given subject, showing top N topics and grouping the rest as Other
     *
     * @param string $subjectName The name of the subject
     * @param int $topN The number of top topics to show
     * @return array An array containing topic names as keys and percentages as values
     */
    public function getQuestionCountPerTopic(string $subjectName, int $topN = 5): array
    {
        $this->logger->info("Starting Method: " . __METHOD__);

        try {
            // First, get main topics count
            $mainTopicsQb = $this->entityManager->createQueryBuilder();
            $mainTopicsQb->select('COUNT(DISTINCT t.name) as mainTopicCount')
                ->from(Question::class, 'q')
                ->join('q.subject', alias: 's')
                ->join(Topic::class, 't', 'WITH', 'q.topic = t.subTopic')
                ->where('s.name = :subjectName')
                ->andWhere('q.topic IS NOT NULL')
                ->setParameter('subjectName', $subjectName);

            $mainTopicsCount = (int) $mainTopicsQb->getQuery()->getSingleScalarResult();

            // If less than 5 main topics, use subtopics instead
            if ($mainTopicsCount < 5) {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('q.topic as subTopic, COUNT(q.id) as questionCount')
                    ->from(Question::class, 'q')
                    ->join('q.subject', alias: 's')
                    ->where('s.name = :subjectName')
                    ->andWhere('q.topic IS NOT NULL')
                    ->groupBy('q.topic')
                    ->orderBy('questionCount', 'DESC')
                    ->setParameter('subjectName', $subjectName);
            } else {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('t.name as mainTopic, COUNT(q.id) as questionCount')
                    ->from(Question::class, 'q')
                    ->join('q.subject', alias: 's')
                    ->join(Topic::class, 't', 'WITH', 'q.topic = t.subTopic')
                    ->where('s.name = :subjectName')
                    ->andWhere('q.topic IS NOT NULL')
                    ->groupBy('t.name')
                    ->orderBy('questionCount', 'DESC')
                    ->setParameter('subjectName', $subjectName);
            }

            $results = $qb->getQuery()->getResult();

            if (empty($results)) {
                return [
                    'status' => 'OK',
                    'data' => []
                ];
            }

            // Filter out "NO MATCH" and "no match" topics and add them to other count
            $filteredResults = [];
            $noMatchCount = 0;

            foreach ($results as $result) {
                $topicName = $mainTopicsCount < 5 ? $result['subTopic'] : $result['mainTopic'];
                if (strtolower($topicName) === 'no match') {
                    $noMatchCount += $result['questionCount'];
                } else {
                    $filteredResults[] = $result;
                }
            }

            // Calculate total questions
            $totalQuestions = array_sum(array_column($filteredResults, 'questionCount')) + $noMatchCount;

            // Process top N topics
            $topTopics = array_slice($filteredResults, 0, $topN);
            $topicPercentages = [];
            $otherCount = $noMatchCount; // Start with NO MATCH count

            foreach ($topTopics as $result) {
                $topicName = $mainTopicsCount < 5 ? $result['subTopic'] : $result['mainTopic'];
                $percentage = round(($result['questionCount'] / $totalQuestions) * 100, 2);
                $topicPercentages[$topicName] = $percentage;
            }

            // Calculate "Other" percentage including remaining topics
            $remainingTopics = array_slice($filteredResults, $topN);
            foreach ($remainingTopics as $result) {
                $otherCount += $result['questionCount'];
            }

            if ($otherCount > 0) {
                $otherPercentage = round(($otherCount / $totalQuestions) * 100, 2);
                $topicPercentages['Other'] = $otherPercentage;
            }

            return [
                'status' => 'OK',
                'data' => $topicPercentages,
                'topicType' => $mainTopicsCount < 5 ? 'subtopics' : 'main_topics'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting question counts per topic: {error}', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'NOK',
                'message' => 'Failed to get question counts per topic',
                'error' => $e->getMessage()
            ];
        }
    }
}