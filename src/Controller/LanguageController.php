<?php

namespace App\Controller;

use App\Service\LanguageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/languages')]
class LanguageController extends AbstractController
{
    private LanguageService $languageService;

    public function __construct(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    #[Route('', name: 'get_languages', methods: ['GET'])]
    public function getLanguages(): JsonResponse
    {
        $languages = $this->languageService->getLanguages();
        $result = array_map(function ($lang) {
            return [
                'id' => $lang->getId(),
                'code' => $lang->getCode(),
                'name' => $lang->getName(),
                'nativeName' => $lang->getNativeName(),
                'enabled' => $lang->isEnabled(),
            ];
        }, $languages);
        return $this->json($result);
    }

    #[Route('/enabled', name: 'get_enabled_languages', methods: ['GET'])]
    public function getEnabledLanguages(): JsonResponse
    {
        $languages = $this->languageService->getEnabledLanguages();
        $result = array_map(function ($lang) {
            return [
                'id' => $lang->getId(),
                'code' => $lang->getCode(),
                'name' => $lang->getName(),
                'nativeName' => $lang->getNativeName(),
            ];
        }, $languages);
        return $this->json($result);
    }

    #[Route('/{code}', name: 'get_language_by_code', methods: ['GET'])]
    public function getLanguageByCode(string $code): JsonResponse
    {
        $language = $this->languageService->getLanguageByCode($code);
        if (!$language) {
            return $this->json(['error' => 'Language not found.'], 404);
        }
        return $this->json([
            'id' => $language->getId(),
            'code' => $language->getCode(),
            'name' => $language->getName(),
            'nativeName' => $language->getNativeName(),
            'enabled' => $language->isEnabled(),
        ]);
    }
}