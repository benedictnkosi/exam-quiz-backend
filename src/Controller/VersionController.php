<?php

namespace App\Controller;

use App\Service\VersionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class VersionController extends AbstractController
{
    private VersionService $versionService;

    public function __construct(VersionService $versionService)
    {
        $this->versionService = $versionService;
    }

    #[Route('/api/version/check', name: 'version_check', methods: ['GET'])]
    public function checkVersion(Request $request): JsonResponse
    {
        $version = $request->query->get('version');

        if (!$version) {
            return $this->json([
                'error' => true,
                'message' => 'Version parameter is required'
            ], 200);
        }

        $result = $this->versionService->isVersionSupported($version);

        return $this->json($result, 200);
    }
}