<?php

namespace App\Controller;

use App\Service\CareerAdviceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CareerAdviceController extends AbstractController
{
    public function __construct(
        private CareerAdviceService $careerAdviceService
    ) {
    }

    #[Route('/api/learner/{uid}/career-advice', name: 'learner_career_advice', methods: ['GET'])]
    public function getCareerAdvice(string $uid): JsonResponse
    {
        $result = $this->careerAdviceService->getCareerAdvice($uid);

        if ($result['status'] === 'NOK') {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result);
    }
}