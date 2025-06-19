<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Learner;
use App\Repository\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MathsService
{
    public function __construct(
        private readonly QuestionRepository $questionRepository,
        private readonly EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get all unique topics from questions where steps is not null and subject grade matches learner grade
     * 
     * @param string $learnerUid The learner's UID
     * @return array Array of unique topics
     */
    public function getTopicsWithSteps(string $learnerUid, string $subjectName): array
    {
        // Get the learner
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);
        if (!$learner) {
            return [];
        }

        $grade = $learner->getGrade();
        if (!$grade) {
            return [];
        }

        // Create query to get unique topics
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT q.topic')
            ->from(Question::class, 'q')
            ->join('q.subject', 's')
            ->where('q.steps IS NOT NULL')
            ->andWhere('s.grade = :grade')
            ->andWhere('q.topic IS NOT NULL')
            ->andWhere('s.name LIKE :subjectName')
            ->setParameter('grade', $grade)
            ->setParameter('subjectName', $subjectName . '%')
            ->orderBy('q.topic', 'ASC');

        $result = $qb->getQuery()->getResult();

        return array_column($result, 'topic');
    }

    /**
     * Get question IDs with steps for a specific topic and grade
     * 
     * @param string $topic The topic to filter by
     * @param int $grade The grade number to filter by
     * @return array Array of question IDs
     */
    public function getQuestionIdsWithSteps(string $topic, int $grade, string $subjectName): array
    {
        // Create query to get question IDs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('q.id')
            ->from(Question::class, 'q')
            ->join('q.subject', 's')
            ->join('s.grade', 'g')
            ->where('q.steps IS NOT NULL')
            ->andWhere('q.topic = :topic')
            ->andWhere('g.number = :grade')
            ->andWhere('q.active = :active')
            ->andWhere('s.name LIKE :subjectName')
            ->setParameter('topic', $topic)
            ->setParameter('grade', $grade)
            ->setParameter('active', true)
            ->setParameter('subjectName', $subjectName . '%')
            ->orderBy('q.id', 'ASC');

        $result = $qb->getQuery()->getResult();

        return array_column($result, 'id');
    }

    /**
     * Get question IDs with steps for a specific topic and grade
     * 
     * @param string $topic The topic name to filter by
     * @param int $grade The grade number to filter by
     * @return array Array of question IDs
     */
    public function getQuestionIdsWithStepsByTopic(string $topic, int $grade, string $subjectName): array
    {
        // Create query to get question IDs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT q.id')
            ->from(Question::class, 'q')
            ->join('q.subject', 's')
            ->join('s.grade', 'g')
            ->join('App\\Entity\\Topic', 't', 'WITH', 'q.topic = t.subTopic')
            ->where('q.steps IS NOT NULL')
            ->andWhere('t.name = :topic')
            ->andWhere('g.number = :grade')
            ->andWhere('q.active = :active')
            ->andWhere('s.name LIKE :subjectName')
            ->setParameter('topic', $topic)
            ->setParameter('grade', $grade)
            ->setParameter('active', true)
            ->setParameter('subjectName', $subjectName . '%')
            ->orderBy('q.id', 'ASC');

        $query = $qb->getQuery();
        $this->logger->debug('[getQuestionIdsWithStepsByTopic] SQL: ' . $query->getSQL() . ' | Params: ' . json_encode($query->getParameters()->map(fn($param) => $param->getValue())->toArray()));

        $result = $query->getResult();

        return array_column($result, 'id');
    }

    /**
     * Get topics and subtopics for questions with steps for a particular grade
     * 
     * @param int $grade The grade number to filter by
     * @param string $subjectName The subject name to filter by
     * @return array Array of topics with their subtopics and question counts
     */
    public function getTopicsAndSubtopicsWithSteps(int $grade, string $subjectName): array
    {
        // Create query to get topics and subtopics with question counts
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT t.name as mainTopic, t.subTopic, COUNT(DISTINCT q.id) as questionCount')
            ->from('App\Entity\Topic', 't')
            ->join('App\Entity\Question', 'q', 'WITH', 'q.topic = t.subTopic')
            ->join('q.subject', 's')
            ->join('s.grade', 'g')
            ->where('q.steps IS NOT NULL')
            ->andWhere('g.number = :grade')
            ->andWhere('q.active = :active')
            ->andWhere('s.name LIKE :subjectName')
            ->andWhere('t.name IS NOT NULL')
            ->andWhere('t.subTopic IS NOT NULL')
            ->setParameter('grade', $grade)
            ->setParameter('active', true)
            ->setParameter('subjectName', $subjectName . '%')
            ->groupBy('t.name, t.subTopic')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.subTopic', 'ASC');

        $result = $qb->getQuery()->getResult();

        // Group results by main topic
        $groupedTopics = [];
        foreach ($result as $row) {
            $mainTopic = $row['mainTopic'];
            $subTopic = $row['subTopic'];
            $questionCount = (int) $row['questionCount'];

            if (!isset($groupedTopics[$mainTopic])) {
                $groupedTopics[$mainTopic] = [
                    'mainTopic' => $mainTopic,
                    'questionCount' => 0,
                    'subtopics' => []
                ];
            }

            // Add to main topic total
            $groupedTopics[$mainTopic]['questionCount'] += $questionCount;

            // Check if subtopic already exists and update question count if needed
            $subtopicExists = false;
            foreach ($groupedTopics[$mainTopic]['subtopics'] as &$existingSubtopic) {
                if ($existingSubtopic['name'] === $subTopic) {
                    $existingSubtopic['questionCount'] = $questionCount;
                    $subtopicExists = true;
                    break;
                }
            }

            if (!$subtopicExists) {
                $groupedTopics[$mainTopic]['subtopics'][] = [
                    'name' => $subTopic,
                    'questionCount' => $questionCount
                ];
            }
        }

        // Convert to indexed array and sort subtopics
        $topics = array_values($groupedTopics);
        foreach ($topics as &$topic) {
            usort($topic['subtopics'], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        return $topics;
    }
}