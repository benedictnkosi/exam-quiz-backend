<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\LearnerProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/learner/profile')]
class LearnerProfileController extends AbstractController
{
    public function __construct(
        private readonly LearnerProfileService $learnerProfileService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/public/{uid}', name: 'learner_profile_update_public', methods: ['PUT'])]
    public function updatePublicProfile(string $uid, Request $request): JsonResponse
    {
        $learner = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);

        if (!$learner) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Learner not found'
            ], 404);
        }

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