<?php

namespace App\Controller;

use App\Entity\Learner;
use App\Service\LearnerProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Grade;

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

    #[Route('/terms/{uid}', name: 'learner_profile_update_terms', methods: ['PUT'])]
    public function updateTerms(string $uid, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $terms = $data['terms'] ?? null;

        if ($terms === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Terms parameter is required'
            ], 400);
        }

        try {
            $this->learnerProfileService->updateLearnerTerms($uid, $terms);
            return $this->json([
                'success' => true,
                'message' => 'Terms updated successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    #[Route('/{uid}', name: 'update_language_learner', methods: ['PUT'])]
    public function updateLanguageLearner(string $uid, Request $request): JsonResponse
    {
        $l = $this->entityManager->getRepository(Learner::class)->findOneBy(['uid' => $uid]);
        if (!$l) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (isset($data['uid']))
            $l->setUid($data['uid']);
        if (isset($data['name']))
            $l->setName($data['name']);
        if (isset($data['grade'])) {
            $grade = $this->entityManager->getRepository(Grade::class)->findOneBy(['number' => $data['grade']]);
            if (!$grade) {
                return $this->json(['error' => 'Grade not found.'], 404);
            }
            $l->setGrade($grade);
        }
        if (isset($data['created']))
            $l->setCreated(new \DateTime($data['created']));
        if (isset($data['lastSeen']))
            $l->setLastSeen(new \DateTime($data['lastSeen']));
        if (isset($data['email']))
            $l->setEmail($data['email']);
        if (isset($data['points']))
            $l->setPoints($data['points']);
        if (isset($data['streak']))
            $l->setStreak($data['streak']);
        if (isset($data['streakLastUpdated']))
            $l->setStreakLastUpdated(new \DateTime($data['streakLastUpdated']));
        if (isset($data['avatar']))
            $l->setAvatar($data['avatar']);
        if (isset($data['expoPushToken']))
            $l->setExpoPushToken($data['expoPushToken']);
        if (isset($data['followMeCode']))
            $l->setFollowMeCode($data['followMeCode']);
        if (isset($data['version']))
            $l->setVersion($data['version']);
        if (isset($data['os']))
            $l->setOs($data['os']);
        if (isset($data['subscription']))
            $l->setSubscription($data['subscription']);
        $this->entityManager->flush();
        return $this->json([
            'id' => $l->getId(),
            'uid' => $l->getUid(),
            'name' => $l->getName(),
            'created' => $l->getCreated()->format(DATE_ATOM),
            'lastSeen' => $l->getLastSeen()->format(DATE_ATOM),
            'email' => $l->getEmail(),
            'points' => $l->getPoints(),
            'streak' => $l->getStreak(),
            'streakLastUpdated' => $l->getStreakLastUpdated()->format(DATE_ATOM),
            'avatar' => $l->getAvatar(),
            'expoPushToken' => $l->getExpoPushToken(),
            'followMeCode' => $l->getFollowMeCode(),
            'version' => $l->getVersion(),
            'os' => $l->getOs(),
            'subscription' => $l->getSubscription(),
            'grade' => $l->getGrade()->getId()
        ]);
    }
}