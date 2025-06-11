<?php

namespace App\Controller;

use App\Entity\LanguageLearner;
use App\Entity\Lesson;
use App\Entity\LanguageLearnerProgress;
use App\Service\LanguageLearnerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/language-learners')]
class LanguageLearnerController extends AbstractController
{
    private EntityManagerInterface $em;
    private LanguageLearnerService $learnerService;

    public function __construct(EntityManagerInterface $em, LanguageLearnerService $learnerService)
    {
        $this->em = $em;
        $this->learnerService = $learnerService;
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

    #[Route('/uid/{uid}', name: 'get_language_learner_by_uid', methods: ['GET'])]
    public function getLanguageLearnerByUid(string $uid): JsonResponse
    {
        $learner = $this->em->getRepository(LanguageLearner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }
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

    #[Route('/{uid}/progress', name: 'add_lesson_progress', methods: ['POST'])]
    public function addLessonProgress(string $uid, Request $request): JsonResponse
    {
        $learner = $this->em->getRepository(LanguageLearner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['lessonId']) || !isset($data['language']) || !isset($data['status'])) {
            return $this->json(['error' => 'Missing required fields: lessonId, language, status'], 400);
        }

        // Find the lesson
        $lesson = $this->em->getRepository(Lesson::class)->find($data['lessonId']);
        if (!$lesson) {
            return $this->json(['error' => 'Lesson not found.'], 404);
        }

        // Create or update progress
        $progress = $this->em->getRepository(LanguageLearnerProgress::class)->findOneBy([
            'learner' => $learner,
            'lesson' => $lesson,
            'language' => $data['language']
        ]);

        if (!$progress) {
            $progress = new LanguageLearnerProgress();
            $progress->setLearner($learner);
            $progress->setLesson($lesson);
            $progress->setUnit($lesson->getUnit());
            $progress->setLanguage($data['language']);
            $progress->setStatus($data['status']);
        } else {
            // Only update status if the lesson is not already completed
            if ($progress->getStatus() !== 'completed') {
                $progress->setStatus($data['status']);
            }
        }

        $progress->setLastUpdate(new \DateTime());

        $this->em->persist($progress);
        $this->em->flush();

        return $this->json([
            'id' => $progress->getId(),
            'learnerUid' => $learner->getUid(),
            'lessonId' => $lesson->getId(),
            'language' => $progress->getLanguage(),
            'status' => $progress->getStatus(),
            'lastUpdate' => $progress->getLastUpdate()->format(DATE_ATOM)
        ]);
    }

    #[Route('/{uid}/progress/{language}', name: 'get_language_progress', methods: ['GET'])]
    public function getLanguageProgress(string $uid, string $language): JsonResponse
    {
        $learner = $this->em->getRepository(LanguageLearner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }

        $progress = $this->em->getRepository(LanguageLearnerProgress::class)->findBy([
            'learner' => $learner,
            'language' => $language
        ]);

        $result = array_map(function ($p) {
            return [
                'id' => $p->getId(),
                'lessonId' => $p->getLesson()->getId(),
                'lessonTitle' => $p->getLesson()->getTitle(),
                'unitId' => $p->getUnit()->getId(),
                'status' => $p->getStatus(),
                'lastUpdate' => $p->getLastUpdate()->format(DATE_ATOM)
            ];
        }, $progress);

        return $this->json($result);
    }

    #[Route('/{uid}/increment-points', name: 'increment_learner_points', methods: ['POST'])]
    public function incrementPoints(string $uid, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['points']) || !is_numeric($data['points'])) {
            return $this->json(['error' => 'Invalid points value'], 400);
        }

        if (!isset($data['lessonId'])) {
            return $this->json(['error' => 'Lesson ID is required'], 400);
        }

        $learner = $this->em->getRepository(LanguageLearner::class)->findOneBy(['uid' => $uid]);
        if (!$learner) {
            return $this->json(['error' => 'Language learner not found.'], 404);
        }

        // Check if the lesson is already completed
        $progress = $this->em->getRepository(LanguageLearnerProgress::class)->findOneBy([
            'learner' => $learner,
            'lesson' => $data['lessonId']
        ]);

        if ($progress && $progress->getStatus() === 'completed') {
            return $this->json([
                'id' => $learner->getId(),
                'uid' => $learner->getUid(),
                'points' => $learner->getPoints(),
                'message' => 'Points not awarded - lesson already completed'
            ]);
        }

        $this->learnerService->incrementPoints($learner, (int) $data['points']);

        return $this->json([
            'id' => $learner->getId(),
            'uid' => $learner->getUid(),
            'points' => $learner->getPoints()
        ]);
    }
}