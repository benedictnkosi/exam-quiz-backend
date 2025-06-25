<?php

namespace App\Service;

use App\Entity\Word;
use App\Entity\WordGroup;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\WordUploadService;

class WordManagementService
{
    private EntityManagerInterface $em;
    private WordUploadService $uploadService;

    public function __construct(EntityManagerInterface $em, WordUploadService $uploadService)
    {
        $this->em = $em;
        $this->uploadService = $uploadService;
    }

    public function addWordGroup(array $data): ?WordGroup
    {
        $group = new WordGroup();
        $group->setName($data['name']);
        $group->setDescription($data['description']);
        $this->em->persist($group);
        $this->em->flush();
        return $group;
    }

    public function addWord(array $data, WordGroup $group): Word
    {
        $word = new Word();
        $word->setWordGroup($group);
        $word->setAudio($data['audio'] ?? []);

        // Convert translations to lowercase
        $translations = $data['translations'] ?? [];
        $lowercaseTranslations = [];
        foreach ($translations as $lang => $translation) {
            $lowercaseTranslations[$lang] = mb_strtolower($translation);
        }
        $word->setTranslations($lowercaseTranslations);

        $word->setImage($data['image'] ?? null);
        
        // Set audio capturers if provided
        if (isset($data['audioCapturers'])) {
            $word->setAudioCapturers($data['audioCapturers']);
        }
        
        $this->em->persist($word);
        $this->em->flush();
        return $word;
    }

    public function deleteWord(int $id): bool
    {
        $word = $this->em->getRepository(Word::class)->find($id);
        if ($word) {
            // Delete audio files
            $audio = $word->getAudio() ?? [];
            foreach ($audio as $audioUrl) {
                $filename = basename(parse_url($audioUrl, PHP_URL_PATH));
                $this->uploadService->removeAudio($filename);
            }
            // Delete image file
            $image = $word->getImage();
            if ($image) {
                $filename = basename($image);
                $this->uploadService->removeImage($filename);
            }
            $this->em->remove($word);
            $this->em->flush();
            return true;
        }
        return false;
    }

    public function deleteWordGroup(int $id): bool
    {
        $group = $this->em->getRepository(WordGroup::class)->find($id);
        if ($group) {
            $this->em->remove($group);
            $this->em->flush();
            return true;
        }
        return false;
    }

    public function addTranslationToWord(int $wordId, string $languageCode, string $translation): ?array
    {
        $word = $this->em->getRepository(\App\Entity\Word::class)->find($wordId);
        if (!$word) {
            return null;
        }
        $translations = $word->getTranslations() ?? [];
        $translations[$languageCode] = mb_strtolower($translation);
        $word->setTranslations($translations);
        $this->em->flush();
        return $translations;
    }

    public function addAudioToWord(int $wordId, string $languageCode, string $audioUrl): ?array
    {
        $word = $this->em->getRepository(\App\Entity\Word::class)->find($wordId);
        if (!$word) {
            return null;
        }
        $audio = $word->getAudio() ?? [];
        $audio[$languageCode] = $audioUrl;
        $word->setAudio($audio);
        $this->em->flush();
        return $audio;
    }

    public function setWordImage(int $wordId, string $imagePath): ?string
    {
        $word = $this->em->getRepository(\App\Entity\Word::class)->find($wordId);
        if (!$word) {
            return null;
        }
        $word->setImage($imagePath);
        $this->em->flush();
        return $imagePath;
    }
}