<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Scalar\String_;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/badges', name: 'api_badges_')]
class BadgeController extends AbstractController
{
    public function __construct(
        private BadgeService $badgeService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/check/{uid}', name: 'check_badges', methods: ['POST'])]
    public function checkAndAssignBadges(Request $request, string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return $this->json([
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ], 404);
            }

            $result = $this->badgeService->checkAndAssignBadges($learner);

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error checking badges: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/learner/{uid}', name: 'get_learner_badges', methods: ['GET'])]
    public function getLearnerBadges(string $uid): JsonResponse
    {
        try {
            $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);

            if (!$learner) {
                return $this->json([
                    'status' => 'NOK',
                    'message' => 'Learner not found'
                ], 404);
            }

            $result = $this->badgeService->getLearnerBadges($learner);

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error fetching badges: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/', name: 'get_all_badges', methods: ['GET'])]
    public function getAllBadges(): JsonResponse
    {
        try {
            $result = $this->badgeService->getAllBadges();
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'NOK',
                'message' => 'Error fetching all badges: ' . $e->getMessage()
            ], 500);
        }
    }
}