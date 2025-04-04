<?php

namespace App\Service;

use App\Entity\Learner;
use App\Entity\LearnerFollowing;
use App\Entity\Result;
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

    public function updateStatus(Learner $follower, Learner $following, string $status): ?LearnerFollowing
    {
        $relationship = $this->repository->findOneBy([
            'follower' => $follower,
            'following' => $following
        ]);

        if (!$relationship) {
            throw new \Exception('Relationship not found');
        }

        //if status is deleted, remove the relationship
        if ($status === 'deleted') {
            $this->entityManager->remove($relationship);
            $this->entityManager->flush();
            return null;
        }
        
        $relationship->setStatus($status);
        $this->entityManager->flush();

        return $relationship;
    }

    public function getFollowing(Learner $learner): array
    {
        $following = $this->repository->findBy([
            'follower' => $learner,
            'status' => 'active'
        ]);

        $result = [];
        foreach ($following as $follow) {
            $followingLearner = $follow->getFollowing();
            
            // Get last result entry
            $lastResult = $this->entityManager->getRepository(Result::class)
                ->findOneBy(['learner' => $followingLearner], ['created' => 'DESC']);
            
            $firstResult = $this->entityManager->getRepository(Result::class)
                ->findOneBy(['learner' => $followingLearner], ['created' => 'ASC']);
            // Get questions answered today
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            $questionsToday = $this->entityManager->getRepository(Result::class)
                ->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.learner = :learner')
                ->andWhere('r.created >= :today')
                ->setParameter('learner', $followingLearner)
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleScalarResult();
            
            // Get questions answered this week
            $weekStart = clone $today;
            $weekStart->modify('-' . $today->format('w') . ' days');
            $questionsThisWeek = $this->entityManager->getRepository(Result::class)
                ->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.learner = :learner')
                ->andWhere('r.created >= :weekStart')
                ->setParameter('learner', $followingLearner)
                ->setParameter('weekStart', $weekStart)
                ->getQuery()
                ->getSingleScalarResult();
            
            $result[] = [
                'learner_uid' => $followingLearner->getUid(),
                'learner_name' => $followingLearner->getName(),
                'points' => $followingLearner->getPoints(),
                'streak' => $followingLearner->getStreak(),
                'lastResult' => $lastResult ? [
                    'id' => $lastResult->getId(),
                    'outcome' => $lastResult->getOutcome(),
                    'created' => $lastResult->getCreated(),
                    'duration' => $lastResult->getDuration()
                ] : null,
                'firstResult' => $firstResult ? [
                    'id' => $firstResult->getId(),
                    'outcome' => $firstResult->getOutcome(),
                    'created' => $firstResult->getCreated(),
                    'duration' => $firstResult->getDuration()
                ] : null,
                'questionsAnsweredToday' => $questionsToday,
                'questionsAnsweredThisWeek' => $questionsThisWeek
            ];
        }

        return $result;
    }

    public function getFollowers(Learner $learner): array
    {
        $followers = $this->repository->findBy([
            'following' => $learner,
            'status' => 'active'
        ]);

        $result = [];
        foreach ($followers as $follow) {
            $follower = $follow->getFollower();
            $result[] = [
                'id' => $follow->getId(),
                'name' => $follower->getName(),
                'uid' => $follower->getUid(),
                'follow_code' => $follower->getFollowMeCode()
            ];
        }

        return $result;
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