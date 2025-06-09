<?php

namespace App\Controller;

use App\Service\UnitManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/units')]
class UnitManagementController extends AbstractController
{
    private UnitManagementService $unitService;

    public function __construct(UnitManagementService $unitService)
    {
        $this->unitService = $unitService;
    }

    #[Route('', name: 'add_unit', methods: ['POST'])]
    public function addUnit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $unit = $this->unitService->addUnit($data);
        return $this->json([
            'id' => $unit->getId(),
            'title' => $unit->getTitle(),
            'unitId' => $unit->getUnitId(),
            'unitOrder' => $unit->getUnitOrder(),
            'availableLanguages' => $unit->getAvailableLanguages(),
        ]);
    }

    #[Route('', name: 'get_units', methods: ['GET'])]
    public function getUnits(): JsonResponse
    {
        $units = $this->unitService->getUnits();
        $result = array_map(function ($unit) {
            return [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'unitId' => $unit->getUnitId(),
                'unitOrder' => $unit->getUnitOrder(),
                'availableLanguages' => $unit->getAvailableLanguages(),
            ];
        }, $units);
        return $this->json($result);
    }

    #[Route('/filter', name: 'get_units_by_language', methods: ['GET'])]
    public function getUnitsByLanguage(Request $request): JsonResponse
    {
        $language = $request->query->get('language');
        if (!$language) {
            return $this->json(['error' => 'language query parameter is required.'], 400);
        }
        $units = $this->unitService->getUnitsByLanguage($language);
        $result = array_map(function ($unit) {
            return [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'unitId' => $unit->getUnitId(),
                'unitOrder' => $unit->getUnitOrder(),
                'availableLanguages' => $unit->getAvailableLanguages(),
            ];
        }, $units);
        return $this->json($result);
    }

    #[Route('/{id}', name: 'delete_unit', methods: ['DELETE'])]
    public function deleteUnit(int $id): JsonResponse
    {
        $success = $this->unitService->deleteUnit($id);
        if (!$success) {
            return $this->json(['error' => 'Unit not found.'], 404);
        }
        return $this->json(['success' => true]);
    }

    #[Route('/{id}', name: 'update_unit', methods: ['PUT'])]
    public function updateUnit(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $unit = $this->unitService->updateUnit($id, $data);
        if (!$unit) {
            return $this->json(['error' => 'Unit not found.'], 404);
        }
        return $this->json([
            'id' => $unit->getId(),
            'title' => $unit->getTitle(),
            'unitId' => $unit->getUnitId(),
            'unitOrder' => $unit->getUnitOrder(),
            'availableLanguages' => $unit->getAvailableLanguages(),
        ]);
    }

    #[Route('/{id}', name: 'get_unit_by_id', methods: ['GET'])]
    public function getUnitById(int $id): JsonResponse
    {
        $unit = $this->unitService->getUnits();
        $unit = array_filter($unit, fn($u) => $u->getId() === $id);
        $unit = array_values($unit);
        if (empty($unit)) {
            return $this->json(['error' => 'Unit not found.'], 404);
        }
        $unit = $unit[0];
        return $this->json([
            'id' => $unit->getId(),
            'title' => $unit->getTitle(),
            'unitId' => $unit->getUnitId(),
            'unitOrder' => $unit->getUnitOrder(),
            'availableLanguages' => $unit->getAvailableLanguages(),
        ]);
    }
}