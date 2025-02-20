<?php

namespace App\Controller;

use App\Service\LearnerRankingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/rankings')]
class LearnerRankingController extends AbstractController
{
    public function __construct(
        private LearnerRankingService $learnerRankingService
    ) {}

    #[Route('/top-learners/{uid}', methods: ['GET'])]
    public function getTopLearners(string $uid): JsonResponse
    {
        $result = $this->learnerRankingService->getTopLearnersWithCurrentPosition($uid);
        
        return $this->json($result, 
            $result['status'] === 'OK' ? 200 : 400
        );
    }
} 