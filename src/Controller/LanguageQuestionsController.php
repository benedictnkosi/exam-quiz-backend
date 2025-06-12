<?php

namespace App\Controller;

use App\Entity\LanguageQuestions;
use App\Entity\LanguageQuestionTypes;
use App\Entity\Lesson;
use App\Entity\Word;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/language-questions')]
class LanguageQuestionsController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('', name: 'add_language_question', methods: ['POST'])]
    public function addLanguageQuestion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $type = $this->em->getRepository(LanguageQuestionTypes::class)->findOneBy(['name' => $data['type']]);
        if (!$type) {
            return $this->json(['error' => 'Question type not found.'], 404);
        }

        // Get options from either content.options or options field
        $options = $data['content']['options'] ?? $data['options'] ?? [];

        // Remove empty options
        $options = array_filter($options, function ($option) {
            return $option !== "" && $option !== null;
        });
        $options = array_values($options); // Re-index array after filtering

        // Check for duplicate options
        if (count($options) !== count(array_unique($options))) {
            return $this->json(['error' => 'Duplicate options are not allowed.'], 400);
        }

        // Validate select_image type
        if ($type->getName() === 'select_image') {
            foreach ($options as $wordId) {
                if (!is_numeric($wordId)) {
                    return $this->json(['error' => 'Invalid word ID format: ' . $wordId], 400);
                }

                $word = $this->em->getRepository(Word::class)->find((int) $wordId);
                if (!$word) {
                    return $this->json(['error' => 'Word not found for ID: ' . $wordId], 404);
                }

                if (!$word->getImage()) {
                    return $this->json(['error' => 'Word with ID ' . $wordId . ' does not have an image set.'], 400);
                }
            }
        }

        // Find lesson if lessonId is provided
        $lesson = null;
        if (isset($data['lessonId'])) {
            $lesson = $this->em->getRepository(Lesson::class)->find($data['lessonId']);
            if (!$lesson) {
                return $this->json(['error' => 'Lesson not found.'], 404);
            }
        }

        $question = new LanguageQuestions();

        // Set matchType if provided in content
        if (isset($data['content']) && isset($data['content']['matchType'])) {
            $question->setMatchType($data['content']['matchType']);
        }

        // Handle content.correct if it exists
        if (isset($data['content']) && isset($data['content']['correct'])) {
            $question->setCorrectOption($data['content']['correct']);
        } else {
            $question->setCorrectOption($data['correctOption'] ?? null);
        }

        // Handle content fields
        if (isset($data['content'])) {
            if (isset($data['content']['possibleAnswers'])) {
                $question->setOptions($data['content']['possibleAnswers']);
                if (isset($data['content']['options'])) {
                    $question->setSentenceWords($data['content']['options']);
                }
            } else if (isset($data['content']['options'])) {
                $question->setOptions($data['content']['options']);
            }

            if (isset($data['content']['sentence'])) {
                $question->setSentenceWords($data['content']['sentence']);
            }
        } else {
            $question->setOptions($options); // Use filtered options
        }

        // Handle content.blankIndex if it exists
        if (isset($data['content']) && isset($data['content']['blankIndex'])) {
            $question->setBlankIndex($data['content']['blankIndex']);
        } else {
            $question->setBlankIndex($data['blankIndex'] ?? null);
        }

        // Handle content.direction if it exists
        if (isset($data['content']) && isset($data['content']['direction'])) {
            $question->setDirection($data['content']['direction']);
        } else {
            $question->setDirection($data['direction'] ?? null);
        }

        $question->setQuestionOrder($data['questionOrder']);
        $question->setType($type);
        if (!isset($data['content']['possibleAnswers']) && !isset($data['content']['sentence'])) {
            $question->setSentenceWords($data['sentenceWords'] ?? null);
        }
        if ($lesson) {
            $question->setLesson($lesson);
        }

        // Check for duplicate questions
        $existingQuestion = $this->em->getRepository(LanguageQuestions::class)->findOneBy([
            'type' => $type,
            'correctOption' => $question->getCorrectOption(),
            'sentenceWords' => $question->getSentenceWords()
        ]);

        if ($existingQuestion) {
            // Compare options arrays
            $existingOptions = $existingQuestion->getOptions();
            $newOptions = $question->getOptions();

            if (
                is_array($existingOptions) && is_array($newOptions) &&
                count($existingOptions) === count($newOptions) &&
                empty(array_diff($existingOptions, $newOptions))
            ) {
                return $this->json([
                    'error' => 'A question with the same options, sentence words, type, and correct option already exists.',
                    'existingQuestionId' => $existingQuestion->getId()
                ], 409);
            }
        }

        $this->em->persist($question);
        $this->em->flush();

        return $this->json([
            'id' => $question->getId(),
            'options' => $question->getOptions(),
            'correctOption' => $question->getCorrectOption(),
            'questionOrder' => $question->getQuestionOrder(),
            'type' => $type->getName(),
            'blankIndex' => $question->getBlankIndex(),
            'sentenceWords' => $question->getSentenceWords(),
            'direction' => $question->getDirection(),
            'lessonId' => $lesson ? $lesson->getId() : null
        ]);
    }

    #[Route('', name: 'get_language_questions', methods: ['GET'])]
    public function getLanguageQuestions(): JsonResponse
    {
        $questions = $this->em->getRepository(LanguageQuestions::class)->findAll();
        $result = array_map(function ($q) {
            return [
                'id' => $q->getId(),
                'options' => $q->getOptions(),
                'correctOption' => $q->getCorrectOption(),
                'questionOrder' => $q->getQuestionOrder(),
                'type' => $q->getType()->getName(),
                'blankIndex' => $q->getBlankIndex(),
                'sentenceWords' => $q->getSentenceWords(),
                'direction' => $q->getDirection(),
            ];
        }, $questions);
        return $this->json($result);
    }

    #[Route('/{id}', name: 'get_language_question', methods: ['GET'])]
    public function getLanguageQuestion(int $id): JsonResponse
    {
        $q = $this->em->getRepository(LanguageQuestions::class)->find($id);
        if (!$q) {
            return $this->json(['error' => 'Language question not found.'], 404);
        }
        return $this->json([
            'id' => $q->getId(),
            'options' => $q->getOptions(),
            'correctOption' => $q->getCorrectOption(),
            'questionOrder' => $q->getQuestionOrder(),
            'type' => $q->getType()->getName(),
            'blankIndex' => $q->getBlankIndex(),
            'sentenceWords' => $q->getSentenceWords(),
            'direction' => $q->getDirection(),
        ]);
    }

    #[Route('/{id}', name: 'update_language_question', methods: ['PUT'])]
    public function updateLanguageQuestion(int $id, Request $request): JsonResponse
    {
        $q = $this->em->getRepository(LanguageQuestions::class)->find($id);
        if (!$q) {
            return $this->json(['error' => 'Language question not found.'], 404);
        }
        $data = json_decode($request->getContent(), true);

        // Validate select_image type if type is being updated or is already select_image
        $type = isset($data['type'])
            ? $this->em->getRepository(LanguageQuestionTypes::class)->findOneBy(['name' => $data['type']])
            : $q->getType();

        if ($type->getName() === 'select_image') {
            $options = $data['content']['options'] ?? $data['options'] ?? $q->getOptions() ?? [];
            foreach ($options as $wordId) {
                if (!is_numeric($wordId)) {
                    return $this->json(['error' => 'Invalid word ID format: ' . $wordId], 400);
                }

                $word = $this->em->getRepository(Word::class)->find((int) $wordId);
                if (!$word) {
                    return $this->json(['error' => 'Word not found for ID: ' . $wordId], 404);
                }

                if (!$word->getImage()) {
                    return $this->json(['error' => 'Word with ID ' . $wordId . ' does not have an image set.'], 400);
                }
            }
        }

        // Handle content.options if it exists
        if (isset($data['content'])) {
            if (isset($data['content']['possibleAnswers'])) {
                $q->setOptions($data['content']['possibleAnswers']);
                // Set sentenceWords as array from options
                if (isset($data['content']['options'])) {
                    $q->setSentenceWords($data['content']['options']);
                }
            } else if (isset($data['content']['options'])) {
                $q->setOptions($data['content']['options']);
            }
        } else if (isset($data['options'])) {
            $q->setOptions($data['options']);
        }

        // Handle content.correct if it exists
        if (isset($data['content']) && isset($data['content']['correct'])) {
            $q->setCorrectOption($data['content']['correct']);
        } else if (array_key_exists('correctOption', $data)) {
            $q->setCorrectOption($data['correctOption']);
        }

        // Handle content.blankIndex if it exists
        if (isset($data['content']) && isset($data['content']['blankIndex'])) {
            $q->setBlankIndex($data['content']['blankIndex']);
        } else if (array_key_exists('blankIndex', $data)) {
            $q->setBlankIndex($data['blankIndex']);
        }

        if (isset($data['questionOrder'])) {
            $q->setQuestionOrder($data['questionOrder']);
        }
        if (isset($data['type'])) {
            $type = $this->em->getRepository(LanguageQuestionTypes::class)->findOneBy(['name' => $data['type']]);
            if ($type) {
                $q->setType($type);
            }
        }
        if (array_key_exists('sentenceWords', $data)) {
            $q->setSentenceWords($data['sentenceWords']);
        }
        if (array_key_exists('sentence', $data)) {
            $q->setSentenceWords($data['sentence']);
        }

        if (array_key_exists('direction', $data)) {
            $q->setDirection($data['direction']);
        }

        // Handle lesson update if lessonId is provided
        if (isset($data['lessonId'])) {
            $lesson = $this->em->getRepository(Lesson::class)->find($data['lessonId']);
            if ($lesson) {
                $q->setLesson($lesson);
            }
        }

        $this->em->flush();
        return $this->json([
            'id' => $q->getId(),
            'options' => $q->getOptions(),
            'correctOption' => $q->getCorrectOption(),
            'questionOrder' => $q->getQuestionOrder(),
            'type' => $q->getType()->getName(),
            'blankIndex' => $q->getBlankIndex(),
            'sentenceWords' => $q->getSentenceWords(),
            'direction' => $q->getDirection(),
            'lessonId' => $q->getLesson() ? $q->getLesson()->getId() : null
        ]);
    }

    #[Route('/{id}', name: 'delete_language_question', methods: ['DELETE'])]
    public function deleteLanguageQuestion(int $id): JsonResponse
    {
        try {
            $q = $this->em->getRepository(LanguageQuestions::class)->find($id);
            if (!$q) {
                return $this->json(['error' => 'Language question not found.'], 404);
            }

            // Store question data before deletion for response
            $deletedQuestion = [
                'id' => $q->getId(),
                'options' => $q->getOptions(),
                'correctOption' => $q->getCorrectOption(),
                'questionOrder' => $q->getQuestionOrder(),
                'type' => $q->getType()->getName(),
                'blankIndex' => $q->getBlankIndex(),
                'sentenceWords' => $q->getSentenceWords(),
                'direction' => $q->getDirection(),
                'lessonId' => $q->getLesson() ? $q->getLesson()->getId() : null
            ];

            $this->em->remove($q);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Question deleted successfully',
                'deletedQuestion' => $deletedQuestion
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete question',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/lesson/{lessonId}', name: 'get_questions_by_lesson', methods: ['GET'])]
    public function getQuestionsByLesson(int $lessonId): JsonResponse
    {
        $questions = $this->em->getRepository(LanguageQuestions::class)->findBy(
            ['lesson' => $lessonId, 'status' => 'approved'],
            ['questionOrder' => 'ASC']
        );
        $result = array_map(function ($q) {
            $options = $q->getOptions();
            $optionsWithResources = [];

            // If options are word IDs, fetch their resources
            if (is_array($options)) {
                foreach ($options as $wordId) {
                    if (is_numeric($wordId)) {
                        $word = $this->em->getRepository(Word::class)->find((int) $wordId);
                        if ($word) {
                            $optionsWithResources[] = [
                                'id' => $word->getId(),
                                'image' => $word->getImage(),
                                'audio' => $word->getAudio(),
                                'translations' => $word->getTranslations()
                            ];
                        }
                    } else {
                        $optionsWithResources[] = $wordId;
                    }
                }
            }

            return [
                'id' => $q->getId(),
                'words' => $optionsWithResources,
                'options' => $q->getOptions(),
                'correctOption' => $q->getCorrectOption(),
                'questionOrder' => $q->getQuestionOrder(),
                'type' => $q->getType()->getName(),
                'blankIndex' => $q->getBlankIndex(),
                'sentenceWords' => $q->getSentenceWords(),
                'direction' => $q->getDirection(),
                'matchType' => $q->getMatchType()
            ];
        }, $questions);
        return $this->json($result);
    }

    #[Route('/{id}/report', name: 'report_question', methods: ['POST'])]
    public function reportQuestion(
        LanguageQuestions $question
    ): JsonResponse {
        $question->setStatus('rejected');

        $this->em->persist($question);
        $this->em->flush();

        return $this->json([
            'message' => 'Question reported successfully',
            'status' => $question->getStatus()
        ]);
    }
}