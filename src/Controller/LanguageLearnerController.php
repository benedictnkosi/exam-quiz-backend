<?php

namespace App\Controller;

use App\Entity\LanguageLearner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/language-learners')]
class LanguageLearnerController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('', name: 'add_language_learner', methods: ['POST'])]
    public function addLanguageLearner(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $learner = new LanguageLearner();
        $learner->setUid($data['uid']);
        $learner->setName($data['name']);
        $learner->setCreated(new \DateTime($data['created'] ?? 'now'));
        $learner->setLastSeen(new \DateTime($data['lastSeen'] ?? 'now'));
        $learner->setEmail($data['email']);
        $learner->setPoints($data['points']);
        $learner->setStreak($data['streak']);
        $learner->setStreakLastUpdated(new \DateTime($data['streakLastUpdated'] ?? 'now'));
        $learner->setAvatar($data['avatar']);
        $learner->setExpoPushToken($data['expoPushToken']);
        $learner->setFollowMeCode($data['followMeCode']);
        $learner->setVersion($data['version']);
        $learner->setOs($data['os']);
        $learner->setReminders($data['reminders']);
        $this->em->persist($learner);
        $this->em->flush();
        return $this->json([
            'id' => $learner->getId(),
            'uid' => $learner->getUid(),
            'name' => $learner->getName(),
            'created' => $learner->getCreated()->format(DATE_ATOM),
            'lastSeen' => $learner->getLastSeen()->format(DATE_ATOM),
            'email' => $learner->getEmail(),
            'points' => $learner->getPoints(),
            'streak' => $learner->getStreak(),
            'streakLastUpdated' => $learner->getStreakLastUpdated()->format(DATE_ATOM),
            'avatar' => $learner->getAvatar(),
            'expoPushToken' => $learner->getExpoPushToken(),
            'followMeCode' => $learner->getFollowMeCode(),
            'version' => $learner->getVersion(),
            'os' => $learner->getOs(),
            'reminders' => $learner->getReminders(),
        ]);
    }

    #[Route('', name: 'get_language_learners', methods: ['GET'])]
    public function getLanguageLearners(): JsonResponse
    {
        $learners = $this->em->getRepository(LanguageLearner::class)->findAll();
        $result = array_map(function ($l) {
            return [
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
                'reminders' => $l->getReminders(),
            ];
        }, $learners);
        return $this->json($result);
    }

    #[Route('/{id}', name: 'get_language_learner', methods: ['GET'])]
    public function getLanguageLearner(int $id): JsonResponse
    {
        $l = $this->em->getRepository(LanguageLearner::class)->find($id);
        if (!$l) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }
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
            'reminders' => $l->getReminders(),
        ]);
    }

    #[Route('/{id}', name: 'update_language_learner', methods: ['PUT'])]
    public function updateLanguageLearner(int $id, Request $request): JsonResponse
    {
        $l = $this->em->getRepository(LanguageLearner::class)->find($id);
        if (!$l) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (isset($data['uid']))
            $l->setUid($data['uid']);
        if (isset($data['name']))
            $l->setName($data['name']);
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
        if (isset($data['reminders']))
            $l->setReminders($data['reminders']);
        $this->em->flush();
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
            'reminders' => $l->getReminders(),
        ]);
    }

    #[Route('/{id}', name: 'delete_language_learner', methods: ['DELETE'])]
    public function deleteLanguageLearner(int $id): JsonResponse
    {
        $l = $this->em->getRepository(LanguageLearner::class)->find($id);
        if (!$l) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }
        $this->em->remove($l);
        $this->em->flush();
        return $this->json(['success' => true]);
    }
}