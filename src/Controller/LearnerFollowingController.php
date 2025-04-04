<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\LearnerFollowingService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learner-following')]
class LearnerFollowingController extends AbstractController
{
    private $serializer;
    private const VALID_STATUSES = ['active', 'blocked', 'rejected'];

    public function __construct(
        private LearnerFollowingService $learnerFollowingService,
        private EntityManagerInterface $entityManager
    ) {
        $this->serializer = SerializerBuilder::create()->build();
    }

    #[Route('/follow/{followerUid}/{followMeCode}', name: 'learner_following_follow', methods: ['POST'])]
    public function followLearner(string $followerUid, string $followMeCode): JsonResponse
    {
        $follower = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $followerUid]);
        $following = $this->entityManager->getRepository(Learner::class)->findOneBy(['followMeCode' => $followMeCode]);

        if (!$follower) {
            return new JsonResponse(['error' => 'Follower learner not found'], 404);
        }

        if (!$following) {
            return new JsonResponse(['error' => 'No learner found with this follow code'], 404);
        }

        if ($follower->getUid() === $following->getUid()) {
            return new JsonResponse(['error' => 'Cannot follow yourself'], 400);
        }

        try {
            if ($this->learnerFollowingService->isFollowing($follower, $following)) {
                return new JsonResponse(['error' => 'Already following this learner'], 400);
            }

            $relationship = $this->learnerFollowingService->followLearner($follower, $following);
            $context = SerializationContext::create()->enableMaxDepthChecks();
            $jsonContent = $this->serializer->serialize([
                'message' => 'Successfully followed learner',
                'data' => $relationship
            ], 'json', $context);
            return new JsonResponse($jsonContent, 200, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/status/{followerUid}/{followingUid}', name: 'learner_following_status', methods: ['PUT'])]
    public function updateStatus(string $followerUid, string $followingUid, Request $request): JsonResponse
    {
        $follower = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $followerUid]);
        $following = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $followingUid]);

        if (!$follower || !$following) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $status = $request->query->get('status');
        if (!in_array($status, self::VALID_STATUSES)) {
            return new JsonResponse(['error' => 'Invalid status. Valid statuses are: ' . implode(', ', self::VALID_STATUSES)], 400);
        }

        try {
            $relationship = $this->learnerFollowingService->updateStatus($follower, $following, $status);
            $context = SerializationContext::create()->enableMaxDepthChecks();
            $jsonContent = $this->serializer->serialize([
                'message' => 'Status updated successfully',
                'data' => $relationship
            ], 'json', $context);
            return new JsonResponse($jsonContent, 200, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/following/{learnerUid}', name: 'learner_following_get_following', methods: ['GET'])]
    public function getFollowing(string $learnerUid): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);

        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $following = $this->learnerFollowingService->getFollowing($learner);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize(['data' => $following], 'json', $context);
        return new JsonResponse($jsonContent, 200, [], true);
    }

    #[Route('/followers/{learnerUid}', name: 'learner_following_get_followers', methods: ['GET'])]
    public function getFollowers(string $learnerUid): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);

        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $followers = $this->learnerFollowingService->getFollowers($learner);
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize(['data' => $followers], 'json', $context);
        return new JsonResponse($jsonContent, 200, [], true);
    }

    #[Route('/counts/{learnerUid}', name: 'learner_following_counts', methods: ['GET'])]
    public function getCounts(string $learnerUid): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $learnerUid]);

        if (!$learner) {
            return new JsonResponse(['error' => 'Learner not found'], 404);
        }

        $counts = [
            'following_count' => $this->learnerFollowingService->getFollowingCount($learner),
            'followers_count' => $this->learnerFollowingService->getFollowersCount($learner)
        ];
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $jsonContent = $this->serializer->serialize($counts, 'json', $context);
        return new JsonResponse($jsonContent, 200, [], true);
    }
} 