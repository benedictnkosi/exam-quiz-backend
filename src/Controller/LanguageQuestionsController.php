<?php

namespace App\Controller;

use App\Entity\LanguageQuestions;
use App\Entity\LanguageQuestionTypes;
use App\Entity\Lesson;
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

        // Find lesson if lessonId is provided
        $lesson = null;
        if (isset($data['lessonId'])) {
            $lesson = $this->em->getRepository(Lesson::class)->find($data['lessonId']);
            if (!$lesson) {
                return $this->json(['error' => 'Lesson not found.'], 404);
            }
        }

        $question = new LanguageQuestions();

        // Handle content.correct if it exists
        if (isset($data['content']) && isset($data['content']['correct'])) {
            $question->setCorrectOption($data['content']['correct']);
        } else {
            $question->setCorrectOption($data['correctOption'] ?? null);
        }

        // Handle content.options if it exists
        if (isset($data['content']) && isset($data['content']['options'])) {
            $question->setOptions($data['content']['options']);
        } else {
            $question->setOptions($data['options']);
        }

        // Handle content.blankIndex if it exists
        if (isset($data['content']) && isset($data['content']['blankIndex'])) {
            $question->setBlankIndex($data['content']['blankIndex']);
        } else {
            $question->setBlankIndex($data['blankIndex'] ?? null);
        }

        $question->setQuestionOrder($data['questionOrder']);
        $question->setType($type);
        $question->setSentenceWords($data['sentenceWords'] ?? null);
        $question->setDirection($data['direction'] ?? null);
        if ($lesson) {
            $question->setLesson($lesson);
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

        // Handle content.options if it exists
        if (isset($data['content']) && isset($data['content']['options'])) {
            $q->setOptions($data['content']['options']);
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
        $questions = $this->em->getRepository(LanguageQuestions::class)->findBy(['lesson' => $lessonId], ['questionOrder' => 'ASC']);
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
}