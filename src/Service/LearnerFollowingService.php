<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerFollowing;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class LearnerFollowingService
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(LearnerFollowing::class);
    }

    public function isFollowing(Learner $follower, Learner $following): bool
    {
        $existing = $this->repository->findOneBy([
            'follower' => $follower,
            'following' => $following,
            'status' => 'active'
        ]);

        return $existing !== null;
    }

    public function followLearner(Learner $follower, Learner $following): LearnerFollowing
    {
        // Check if relationship already exists
        $existing = $this->repository->findOneBy([
            'follower' => $follower,
            'following' => $following
        ]);

        if ($existing) {
            if ($existing->getStatus() === 'blocked') {
                throw new \Exception('Cannot follow a blocked learner');
            }
            if ($existing->getStatus() === 'active') {
                throw new \Exception('Already following this learner');
            }
            // If status was something else, update it to active
            $existing->setStatus('active');
            $this->entityManager->flush();
            return $existing;
        }

        $learnerFollowing = new LearnerFollowing();
        $learnerFollowing->setFollower($follower);
        $learnerFollowing->setFollowing($following);
        $learnerFollowing->setStatus('active');

        $this->entityManager->persist($learnerFollowing);
        $this->entityManager->flush();

        return $learnerFollowing;
    }

    public function updateStatus(Learner $follower, Learner $following, string $status): LearnerFollowing
    {
        $relationship = $this->repository->findOneBy([
            'follower' => $follower,
            'following' => $following
        ]);

        if (!$relationship) {
            throw new \Exception('Relationship not found');
        }

        $relationship->setStatus($status);
        $this->entityManager->flush();

        return $relationship;
    }

    public function getFollowing(Learner $learner): array
    {
        return $this->repository->findBy([
            'follower' => $learner,
            'status' => 'active'
        ]);
    }

    public function getFollowers(Learner $learner): array
    {
        return $this->repository->findBy([
            'following' => $learner,
            'status' => 'active'
        ]);
    }

    public function getFollowingCount(Learner $learner): int
    {
        return $this->repository->count([
            'follower' => $learner,
            'status' => 'active'
        ]);
    }

    public function getFollowersCount(Learner $learner): int
    {
        return $this->repository->count([
            'following' => $learner,
            'status' => 'active'
        ]);
    }
} 