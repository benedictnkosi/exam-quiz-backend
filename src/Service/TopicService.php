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
}