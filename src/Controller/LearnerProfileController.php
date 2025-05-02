<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\LearnerProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/learner/profile')]
class LearnerProfileController extends AbstractController
{
    public function __construct(
        private readonly LearnerProfileService $learnerProfileService
    ) {
    }

    #[Route('/public', name: 'learner_profile_update_public', methods: ['PUT'])]
    public function updatePublicProfile(Request $request): JsonResponse
    {
        /** @var Learner $learner */
        $learner = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $isPublic = $data['isPublic'] ?? true;

        $learner = $this->learnerProfileService->updatePublicProfile($learner, $isPublic);

        return $this->json([
            'success' => true,
            'data' => [
                'publicProfile' => $learner->getPublicProfile()
            ]
        ]);
    }
}