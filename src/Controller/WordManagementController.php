<?php

namespace App\Controller;

use App\Entity\WordGroup;
use App\Service\WordManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api/words')]
class WordManagementController extends AbstractController
{
    private WordManagementService $wordService;
    private EntityManagerInterface $em;

    public function __construct(WordManagementService $wordService, EntityManagerInterface $em)
    {
        $this->wordService = $wordService;
        $this->em = $em;
    }

    #[Route('/group', name: 'add_word_group', methods: ['POST'])]
    public function addWordGroup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $group = $this->wordService->addWordGroup($data);
        if (!$group) {
            return $this->json(['error' => 'A word group with this id already exists.'], 409);
        }
        return $this->json([
            'id' => $group->getId(),
            'name' => $group->getName(),
            'description' => $group->getDescription()
        ]);
    }

    #[Route('/word', name: 'add_word', methods: ['POST'])]
    public function addWord(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['groupId'])) {
            return $this->json(['error' => 'groupId is required.'], 400);
        }
        $group = $this->em->getRepository(WordGroup::class)->find($data['groupId']);
        if (!$group) {
            return $this->json(['error' => 'Word group not found'], Response::HTTP_NOT_FOUND);
        }
        $word = $this->wordService->addWord($data, $group);
        return $this->json([
            'id' => $word->getId(),
            'audio' => $word->getAudio(),
            'translations' => $word->getTranslations(),
            'groupId' => $group->getId(),
        ]);
    }

    #[Route('/word/{id}', name: 'delete_word', methods: ['DELETE'])]
    public function deleteWord(int $id): JsonResponse
    {
        $success = $this->wordService->deleteWord($id);
        return $this->json(['success' => $success]);
    }

    #[Route('/group/{id}', name: 'delete_word_group', methods: ['DELETE'])]
    public function deleteWordGroup(int $id): JsonResponse
    {
        $success = $this->wordService->deleteWordGroup($id);
        return $this->json(['success' => $success]);
    }

    #[Route('/word/{id}/translation', name: 'add_translation_to_word', methods: ['PUT'])]
    public function addTranslationToWord(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['languageCode'], $data['translation'])) {
            return $this->json(['error' => 'languageCode and translation are required.'], 400);
        }
        $translations = $this->wordService->addTranslationToWord($id, $data['languageCode'], $data['translation']);
        if ($translations === null) {
            return $this->json(['error' => 'Word not found.'], 404);
        }
        return $this->json(['translations' => $translations]);
    }

    #[Route('/word/{id}/audio', name: 'add_audio_to_word', methods: ['PUT'])]
    public function addAudioToWord(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['languageCode'], $data['audioUrl'])) {
            return $this->json(['error' => 'languageCode and audioUrl are required.'], 400);
        }
        $audio = $this->wordService->addAudioToWord($id, $data['languageCode'], $data['audioUrl']);
        if ($audio === null) {
            return $this->json(['error' => 'Word not found.'], 404);
        }
        return $this->json(['audio' => $audio]);
    }

    #[Route('/all', name: 'get_all_words', methods: ['GET'])]
    public function getAllWords(): JsonResponse
    {
        $words = $this->em->getRepository(\App\Entity\Word::class)->findAll();
        $result = array_map(function ($word) {
            return [
                'id' => $word->getId(),
                'audio' => $word->getAudio(),
                'translations' => $word->getTranslations(),
                'groupId' => $word->getWordGroup()->getId(),
                'image' => $word->getImage(),
            ];
        }, $words);
        return $this->json($result);
    }

    #[Route('/group/{groupId}', name: 'get_words_by_group', methods: ['GET'])]
    public function getWordsByGroup(int $groupId): JsonResponse
    {
        $words = $this->em->getRepository(\App\Entity\Word::class)->findBy(['wordGroup' => $groupId]);
        $result = array_map(function ($word) {
            return [
                'id' => $word->getId(),
                'audio' => $word->getAudio(),
                'translations' => $word->getTranslations(),
                'groupId' => $word->getWordGroup()->getId(),
                'image' => $word->getImage(),
            ];
        }, $words);
        return $this->json($result);
    }

    #[Route('/groups', name: 'get_word_groups', methods: ['GET'])]
    public function getWordGroups(): JsonResponse
    {
        $groups = $this->em->getRepository(\App\Entity\WordGroup::class)->findAll();
        $result = array_map(function ($group) {
            return [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'description' => $group->getDescription()
            ];
        }, $groups);
        return $this->json($result);
    }

    #[Route('/word/{id}', name: 'get_word_by_id', methods: ['GET'])]
    public function getWordById(int $id): JsonResponse
    {
        $word = $this->em->getRepository(\App\Entity\Word::class)->find($id);
        if (!$word) {
            return $this->json(['error' => 'Word not found.'], 404);
        }
        return $this->json([
            'id' => $word->getId(),
            'audio' => $word->getAudio(),
            'translations' => $word->getTranslations(),
            'groupId' => $word->getWordGroup()->getId(),
        ]);
    }

    #[Route('/word/{id}', name: 'update_word', methods: ['PUT'])]
    public function updateWord(int $id, Request $request): JsonResponse
    {
        $word = $this->em->getRepository(\App\Entity\Word::class)->find($id);
        if (!$word) {
            return $this->json(['error' => 'Word not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['audio'])) {
            $word->setAudio($data['audio']);
        }

        if (isset($data['translations'])) {
            $word->setTranslations($data['translations']);
        }

        if (isset($data['groupId'])) {
            $group = $this->em->getRepository(WordGroup::class)->find($data['groupId']);
            if (!$group) {
                return $this->json(['error' => 'Word group not found'], 404);
            }
            $word->setWordGroup($group);
        }

        $this->em->flush();

        return $this->json([
            'id' => $word->getId(),
            'audio' => $word->getAudio(),
            'translations' => $word->getTranslations(),
            'groupId' => $word->getWordGroup()->getId(),
            'image' => $word->getImage()
        ]);
    }
}