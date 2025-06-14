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
use App\Entity\Unit;
use App\Entity\Lesson;
use App\Entity\LanguageQuestions;
use App\Entity\Word;
use App\Entity\Learner;

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
        if (!isset($data['languageCode'], $data['audioUrl'], $data['uid'])) {
            return $this->json(['error' => 'languageCode, audioUrl and uid are required.'], 400);
        }

        $word = $this->em->getRepository(Word::class)->find($id);
        if (!$word) {
            return $this->json(['error' => 'Word not found.'], 404);
        }

        $learner = $this->em->getRepository(Learner::class)->findOneBy(['uid' => $data['uid']]);
        if (!$learner) {
            return $this->json(['error' => 'Learner not found.'], 404);
        }

        // Get current audio and capturers
        $currentAudio = $word->getAudio() ?? [];
        $currentCapturers = $word->getAudioCapturers() ?? [];

        // Add new audio
        $currentAudio[$data['languageCode']] = $data['audioUrl'];
        $word->setAudio($currentAudio);

        // Create new capturer info
        $capturerInfo = [
            'learnerId' => $learner->getId(),
            'languageCode' => $data['languageCode'],
            'date' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        // Find and replace existing entry for this language, or add new one
        $found = false;
        foreach ($currentCapturers as $key => $capturer) {
            if ($capturer['languageCode'] === $data['languageCode']) {
                $currentCapturers[$key] = $capturerInfo;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $currentCapturers[] = $capturerInfo;
        }

        $word->setAudioCapturers($currentCapturers);
        $this->em->flush();

        return $this->json([
            'audio' => $word->getAudio(),
            'audioCapturers' => $word->getAudioCapturers()
        ]);
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

    #[Route('/unit/{unitId}', name: 'get_words_by_unit', methods: ['GET'])]
    public function getWordsByUnit(int $unitId): JsonResponse
    {
        // Get the unit
        $unit = $this->em->getRepository(Unit::class)->find($unitId);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found.'], 404);
        }

        // Get all lessons for this unit
        $lessons = $this->em->getRepository(Lesson::class)->findBy(['unit' => $unitId]);

        $words = [];
        $processedWordIds = [];

        // For each lesson, get its questions
        foreach ($lessons as $lesson) {
            $questions = $this->em->getRepository(LanguageQuestions::class)->findBy(['lesson' => $lesson->getId()]);

            // For each question, get its words from options
            foreach ($questions as $question) {
                $options = $question->getOptions();
                if (is_array($options)) {
                    foreach ($options as $option) {
                        if (is_numeric($option)) {
                            $wordId = (int) $option;
                            // Only process each word once
                            if (!in_array($wordId, $processedWordIds)) {
                                $word = $this->em->getRepository(Word::class)->find($wordId);
                                if ($word) {
                                    $words[] = [
                                        'id' => $word->getId(),
                                        'audio' => $word->getAudio(),
                                        'translations' => $word->getTranslations(),
                                        'groupId' => $word->getWordGroup()->getId(),
                                        'image' => $word->getImage(),
                                    ];
                                    $processedWordIds[] = $wordId;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->json($words);
    }

    #[Route('/report/recordings', name: 'get_recordings_report', methods: ['GET'])]
    public function getRecordingsReport(Request $request): JsonResponse
    {
        $fromDate = $request->query->get('fromDate');
        $endDate = $request->query->get('endDate');

        if (!$fromDate || !$endDate) {
            return $this->json(['error' => 'fromDate and endDate are required.'], 400);
        }

        try {
            $fromDateTime = new \DateTime($fromDate);
            $endDateTime = new \DateTime($endDate);
            $endDateTime->setTime(23, 59, 59); // Include the entire end date
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
        }

        // Get all words with audio capturers
        $words = $this->em->getRepository(Word::class)->findAll();

        $report = [];
        $learnerStats = [];

        foreach ($words as $word) {
            $audioCapturers = $word->getAudioCapturers() ?? [];

            foreach ($audioCapturers as $capturer) {
                $captureDate = new \DateTime($capturer['date']);

                // Check if the capture date is within the range
                if ($captureDate >= $fromDateTime && $captureDate <= $endDateTime) {
                    $learnerId = $capturer['learnerId'];
                    $languageCode = $capturer['languageCode'];

                    // Initialize learner stats if not exists
                    if (!isset($learnerStats[$learnerId])) {
                        $learner = $this->em->getRepository(Learner::class)->find($learnerId);
                        $learnerStats[$learnerId] = [
                            'learnerId' => $learnerId,
                            'learnerName' => $learner ? $learner->getName() : 'Unknown',
                            'totalRecordings' => 0,

                        ];
                    }

                    // Update stats
                    $learnerStats[$learnerId]['totalRecordings']++;


                }
            }
        }

        // Convert to array and sort by total recordings
        $report = array_values($learnerStats);
        usort($report, function ($a, $b) {
            return $b['totalRecordings'] - $a['totalRecordings'];
        });

        return $this->json([
            'fromDate' => $fromDate,
            'endDate' => $endDate,
            'report' => $report
        ]);
    }
}