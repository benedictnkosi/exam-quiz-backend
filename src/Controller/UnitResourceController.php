<?php

namespace App\Controller;

use App\Service\UnitResourceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/unit-resources')]
class UnitResourceController extends AbstractController
{
    private UnitResourceService $unitResourceService;

    public function __construct(UnitResourceService $unitResourceService)
    {
        $this->unitResourceService = $unitResourceService;
    }

    #[Route('/{unitId}/{language}', name: 'get_unit_resources', methods: ['GET'])]
    public function getUnitResources(int $unitId, string $language): JsonResponse
    {
        $result = $this->unitResourceService->getUnitResources($unitId, $language);

        if (isset($result['error'])) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}