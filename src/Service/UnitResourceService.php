<?php

namespace App\Service;

use App\Entity\Unit;
use App\Entity\Lesson;
use App\Entity\LanguageQuestions;
use App\Entity\Word;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UnitResourceService
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
    }

    public function getUnitResources(int $unitId, string $language): array
    {
        $this->logger->info('Starting to fetch resources for unit {unitId} and language {language}', [
            'unitId' => $unitId,
            'language' => $language
        ]);

        // Get the unit
        $unit = $this->em->getRepository(Unit::class)->find($unitId);
        if (!$unit) {
            $this->logger->warning('Unit not found', [
                'unitId' => $unitId
            ]);
            return ['error' => 'Unit not found'];
        }

        $this->logger->info('Found unit: {unitTitle}', [
            'unitTitle' => $unit->getTitle()
        ]);

        // Get all lessons for this unit
        $lessons = $this->em->getRepository(Lesson::class)->findBy(['unit' => $unitId]);
        $this->logger->info('Found {lessonCount} lessons for unit', [
            'lessonCount' => count($lessons)
        ]);

        $audioFiles = [];
        $images = [];
        $processedWords = [];

        // For each lesson, get its questions
        foreach ($lessons as $lesson) {
            $this->logger->debug('Processing lesson: {lessonId} - {lessonTitle}', [
                'lessonId' => $lesson->getId(),
                'lessonTitle' => $lesson->getTitle()
            ]);

            $questions = $this->em->getRepository(LanguageQuestions::class)->findBy(['lesson' => $lesson->getId()]);
            $this->logger->debug('Found {questionCount} questions for lesson', [
                'questionCount' => count($questions)
            ]);

            // For each question, get its words
            foreach ($questions as $question) {
                $this->logger->debug('Processing question: {questionId}', [
                    'questionId' => $question->getId(),
                    'questionType' => $question->getType()->getName()
                ]);

                $options = $question->getOptions();
                if ($options) {
                    $this->logger->debug('Question {questionId} has {optionCount} options', [
                        'questionId' => $question->getId(),
                        'optionCount' => count($options)
                    ]);

                    foreach ($options as $wordId) {
                        // Skip if we've already processed this word
                        if (in_array($wordId, $processedWords)) {
                            $this->logger->debug('Skipping already processed word: {wordId}', [
                                'wordId' => $wordId
                            ]);
                            continue;
                        }
                        $processedWords[] = $wordId;

                        $word = $this->em->getRepository(Word::class)->find($wordId);
                        if ($word) {
                            // Check if the word has translations for the requested language
                            $translations = $word->getTranslations();
                            if ($translations && isset($translations[$language])) {
                                // Add audio file if available in the requested language
                                $audio = $word->getAudio();
                                if ($audio && isset($audio[$language])) {
                                    $audioFiles[] = $audio[$language];
                                    $this->logger->debug('Added audio file for word {wordId}', [
                                        'wordId' => $wordId,
                                        'audioFile' => $audio[$language]
                                    ]);
                                }

                                // Add image if available
                                $image = $word->getImage();
                                if ($image) {
                                    $images[] = $image;
                                    $this->logger->debug('Added image for word {wordId}', [
                                        'wordId' => $wordId,
                                        'image' => $image
                                    ]);
                                }
                            } else {
                                $this->logger->debug('Word {wordId} has no translation for language {language}', [
                                    'wordId' => $wordId,
                                    'language' => $language,
                                    'questionId' => $question->getId(),
                                    'availableTranslations' => array_keys($translations ?? [])
                                ]);
                            }
                        } else {
                            $this->logger->warning('Word not found', [
                                'wordId' => $wordId,
                                'questionId' => $question->getId()
                            ]);
                        }
                    }
                } else {
                    $this->logger->debug('Question {questionId} has no options', [
                        'questionId' => $question->getId()
                    ]);
                }
            }
        }

        $this->logger->info('Completed fetching resources', [
            'unitId' => $unitId,
            'language' => $language,
            'audioFileCount' => count($audioFiles),
            'imageCount' => count($images)
        ]);

        return [
            'audio' => array_unique($audioFiles),
            'images' => array_unique($images)
        ];
    }
}