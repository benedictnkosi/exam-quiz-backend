<?php

namespace App\Service;

use App\Entity\Unit;
use Doctrine\ORM\EntityManagerInterface;

class UnitManagementService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function addUnit(array $data): Unit
    {
        $unit = new Unit();
        $unit->setTitle($data['title']);
        $unit->setUnitId($data['unitId']);
        $unit->setUnitOrder($data['unitOrder']);
        $unit->setAvailableLanguages($data['availableLanguages'] ?? []);
        $this->em->persist($unit);
        $this->em->flush();
        return $unit;
    }

    public function getUnits(): array
    {
        return $this->em->getRepository(Unit::class)->findAll();
    }

    public function getUnitsByLanguage(string $language): array
    {
        $allUnits = $this->getUnits();
        $filteredUnits = array_filter($allUnits, function ($unit) use ($language) {
            $langs = $unit->getAvailableLanguages() ?? [];
            return in_array($language, $langs);
        });

        // Sort units by unitOrder
        usort($filteredUnits, function ($a, $b) {
            return $a->getUnitOrder() - $b->getUnitOrder();
        });

        return $filteredUnits;
    }

    public function deleteUnit(int $id): bool
    {
        $unit = $this->em->getRepository(\App\Entity\Unit::class)->find($id);
        if ($unit) {
            $this->em->remove($unit);
            $this->em->flush();
            return true;
        }
        return false;
    }

    public function updateUnit(int $id, array $data): ?\App\Entity\Unit
    {
        $unit = $this->em->getRepository(\App\Entity\Unit::class)->find($id);
        if (!$unit) {
            return null;
        }
        if (isset($data['title'])) {
            $unit->setTitle($data['title']);
        }
        if (isset($data['unitId'])) {
            $unit->setUnitId($data['unitId']);
        }
        if (isset($data['unitOrder'])) {
            $unit->setUnitOrder($data['unitOrder']);
        }
        if (isset($data['availableLanguages'])) {
            $unit->setAvailableLanguages($data['availableLanguages']);
        }
        $this->em->flush();
        return $unit;
    }
}