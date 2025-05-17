<?php

namespace App\Service;

use App\Entity\StoryArc;
use App\Repository\StoryArcRepository;
use Doctrine\ORM\EntityManagerInterface;

class StoryArcService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StoryArcRepository $storyArcRepository
    ) {
    }

    private function getNextAvailablePublishDate(): \DateTime
    {
        // Get the latest publish date from the StoryArc table only
        $latestArc = $this->entityManager->getRepository('App\\Entity\\StoryArc')
            ->createQueryBuilder('a')
            ->orderBy('a.publishDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $arcDate = null;
        if ($latestArc && $latestArc->getPublishDate()) {
            $arcDate = $latestArc->getPublishDate();
        }

        if ($arcDate) {
            $baseDate = $arcDate;
        } else {
            $baseDate = new \DateTime('tomorrow');
        }

        $nextDate = clone $baseDate;
        $nextDate->modify('+1 day');
        $nextDate->setTime(18, 0, 0);
        while ($nextDate->format('N') >= 6) {
            $nextDate->modify('+1 day');
        }
        return $nextDate;
    }

    public function createStoryArc(array $data): StoryArc
    {
        $storyArc = new StoryArc();
        $storyArc->setTheme($data['theme']);
        $storyArc->setGoal($data['goal']);
        $storyArc->setPublishDate($this->getNextAvailablePublishDate());
        $storyArc->setChapterName($data['chapter_name']);
        $storyArc->setOutline($data['outline']);

        // Get the next available chapter number
        $lastArc = $this->entityManager->getRepository('App\\Entity\\StoryArc')
            ->createQueryBuilder('a')
            ->orderBy('a.chapterNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextChapterNumber = $lastArc ? $lastArc->getChapterNumber() + 1 : 1;
        $storyArc->setChapterNumber($nextChapterNumber);

        if (isset($data['status'])) {
            $storyArc->setStatus($data['status']);
        }

        $this->entityManager->persist($storyArc);
        $this->entityManager->flush();

        return $storyArc;
    }

    public function getAllStoryArcs(): array
    {
        return $this->storyArcRepository->findAll();
    }

    public function getStoryArcById(int $id): ?StoryArc
    {
        return $this->storyArcRepository->find($id);
    }

    public function updateStoryArc(int $id, array $data): ?StoryArc
    {
        $storyArc = $this->getStoryArcById($id);
        if (!$storyArc) {
            return null;
        }

        if (isset($data['theme'])) {
            $storyArc->setTheme($data['theme']);
        }
        if (isset($data['goal'])) {
            $storyArc->setGoal($data['goal']);
        }
        if (isset($data['publish_date'])) {
            $storyArc->setPublishDate(new \DateTime($data['publish_date']));
        }
        if (isset($data['chapter_name'])) {
            $storyArc->setChapterName($data['chapter_name']);
        }
        if (isset($data['outline'])) {
            $storyArc->setOutline($data['outline']);
        }
        if (isset($data['status'])) {
            $storyArc->setStatus($data['status']);
        }

        $this->entityManager->flush();

        return $storyArc;
    }

    public function deleteStoryArc(int $id): bool
    {
        $storyArc = $this->getStoryArcById($id);
        if (!$storyArc) {
            return false;
        }

        $this->entityManager->remove($storyArc);
        $this->entityManager->flush();

        return true;
    }

    public function createBulkStoryArcs(array $storyArcsData): array
    {
        $createdStoryArcs = [];
        $errors = [];

        // Get the latest chapter number
        $lastArc = $this->entityManager->getRepository('App\\Entity\\StoryArc')
            ->createQueryBuilder('a')
            ->orderBy('a.chapterNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextChapterNumber = $lastArc ? $lastArc->getChapterNumber() + 1 : 1;

        // Get the latest publish date from the StoryArc table only
        $latestArc = $this->entityManager->getRepository('App\\Entity\\StoryArc')
            ->createQueryBuilder('a')
            ->orderBy('a.publishDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $arcDate = null;
        if ($latestArc && $latestArc->getPublishDate()) {
            $arcDate = $latestArc->getPublishDate();
        }

        if ($arcDate) {
            $nextDate = clone $arcDate;
            $nextDate->modify('+1 day');
        } else {
            $nextDate = new \DateTime('tomorrow');
        }
        // Set to 18:00 and skip weekends if needed
        $nextDate->setTime(18, 0, 0);
        while ($nextDate->format('N') >= 6) {
            $nextDate->modify('+1 day');
        }

        foreach ($storyArcsData as $index => $data) {
            try {
                $storyArc = new StoryArc();
                $storyArc->setTheme($data['theme']);
                $storyArc->setGoal($data['goal']);
                $storyArc->setPublishDate(clone $nextDate);
                $storyArc->setChapterName($data['chapter_name']);
                $storyArc->setOutline($data['outline']);
                $storyArc->setChapterNumber($nextChapterNumber++);
                if (isset($data['status'])) {
                    $storyArc->setStatus($data['status']);
                }
                $this->entityManager->persist($storyArc);
                $createdStoryArcs[] = $storyArc;

                // Prepare next date (increment, skip weekends, set to 18:00)
                $nextDate->modify('+1 day');
                while ($nextDate->format('N') >= 6) {
                    $nextDate->modify('+1 day');
                }
                $nextDate->setTime(18, 0, 0);
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($createdStoryArcs)) {
            $this->entityManager->flush();
        }

        return [
            'created' => array_map(function ($storyArc) {
                return [
                    'id' => $storyArc->getId(),
                    'theme' => $storyArc->getTheme(),
                    'goal' => $storyArc->getGoal(),
                    'publish_date' => $storyArc->getPublishDate()->format('Y-m-d H:i:s'),
                    'chapter_name' => $storyArc->getChapterName(),
                    'outline' => $storyArc->getOutline(),
                    'status' => $storyArc->getStatus(),
                    'chapter_number' => $storyArc->getChapterNumber()
                ];
            }, $createdStoryArcs),
            'errors' => $errors
        ];
    }
}