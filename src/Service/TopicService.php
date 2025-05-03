<?php

namespace App\Service;

use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TopicService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get all topics from the database
     *
     * @return array<Topic>
     */
    public function getAllTopics(): array
    {
        return $this->entityManager
            ->getRepository(Topic::class)
            ->findAll();
    }

    /**
     * Get a topic by its ID
     *
     * @param int $id The topic ID
     * @return Topic|null The topic or null if not found
     */
    public function getTopicById(int $id): ?Topic
    {
        return $this->entityManager
            ->getRepository(Topic::class)
            ->find($id);
    }

    /**
     * Update the image file name for a topic
     *
     * @param Topic $topic The topic to update
     * @param string|null $imageFileName The new image file name
     * @return bool True if the update was successful, false otherwise
     */
    public function updateTopicImage(Topic $topic, ?string $imageFileName): bool
    {
        try {
            $topic->setImageFileName($imageFileName);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update topic image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the topic with the most questions for a specific grade and term that hasn't been posted yet
     *
     * @param int $grade The grade number
     * @param string $term The term
     * @return Topic|null The topic with the most questions or null if none found
     */
    public function getTopicWithMostQuestions(int $grade, string $term): ?Topic
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('t, COUNT(q.id) as questionCount')
                ->from(Topic::class, 't')
                ->join('t.subject', 's')
                ->leftJoin('App\Entity\Question', 'q', 'WITH', 'q.topic = t.subTopic AND q.subject = s')
                ->where('s.grade = :grade')
                ->andWhere('q.term = :term')
                ->andWhere('t.postedDate IS NULL')
                ->groupBy('t.id')
                ->orderBy('questionCount', 'DESC')
                ->setMaxResults(1)
                ->setParameter('grade', $grade)
                ->setParameter('term', $term);

            $result = $qb->getQuery()->getOneOrNullResult();

            return $result ? $result[0] : null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting topic with most questions: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update the posted date of a topic
     *
     * @param Topic $topic The topic to update
     * @param \DateTimeInterface|null $postedDate The new posted date (null to unset)
     * @return bool True if the update was successful, false otherwise
     */
    public function updatePostedDate(Topic $topic, ?\DateTimeInterface $postedDate): bool
    {
        try {
            $topic->setPostedDate($postedDate);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update topic posted date: ' . $e->getMessage());
            return false;
        }
    }
}