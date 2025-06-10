<?php

namespace App\Service;

use App\Entity\Languages;
use Doctrine\ORM\EntityManagerInterface;

class LanguageService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getLanguages(): array
    {
        return $this->em->getRepository(Languages::class)->findAll();
    }

    public function getEnabledLanguages(): array
    {
        return $this->em->getRepository(Languages::class)->findBy(['enabled' => true]);
    }

    public function getLanguageByCode(string $code): ?Languages
    {
        return $this->em->getRepository(Languages::class)->findOneBy(['code' => $code]);
    }
}