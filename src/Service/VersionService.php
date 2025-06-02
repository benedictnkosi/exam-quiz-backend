<?php

namespace App\Service;

use App\Entity\Version;
use Doctrine\ORM\EntityManagerInterface;

class VersionService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function isVersionSupported(string $version): array
    {
        $versionEntity = $this->entityManager->getRepository(Version::class)->findOneBy(['version' => $version]);

        if (!$versionEntity) {
            return [
                'supported' => false,
                'message' => 'Version is not supported',
                'version' => $version
            ];
        }

        if (!$versionEntity->getIsSupported()) {
            return [
                'supported' => false,
                'message' => 'Version is no longer supported',
                'version' => $version,
                'deprecatedAt' => $versionEntity->getDeprecatedAt()?->format('Y-m-d H:i:s')
            ];
        }

        return [
            'supported' => true,
            'message' => 'Version is supported',
            'version' => $version
        ];
    }

    public function addVersion(string $version, bool $isSupported = true): Version
    {
        $versionEntity = new Version();
        $versionEntity->setVersion($version);
        $versionEntity->setIsSupported($isSupported);
        $versionEntity->setCreatedAt(new \DateTime());

        $this->entityManager->persist($versionEntity);
        $this->entityManager->flush();

        return $versionEntity;
    }

    public function deprecateVersion(string $version): ?Version
    {
        $versionEntity = $this->entityManager->getRepository(Version::class)->findOneBy(['version' => $version]);

        if (!$versionEntity) {
            return null;
        }

        $versionEntity->setIsSupported(false);
        $versionEntity->setDeprecatedAt(new \DateTime());

        $this->entityManager->persist($versionEntity);
        $this->entityManager->flush();

        return $versionEntity;
    }
}