<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'language_unit')]
class Unit
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $unitId;

    #[ORM\Column(name: 'unit_order', type: 'integer')]
    private int $unitOrder;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $availableLanguages = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getUnitId(): string
    {
        return $this->unitId;
    }

    public function setUnitId(string $unitId): self
    {
        $this->unitId = $unitId;
        return $this;
    }

    public function getUnitOrder(): int
    {
        return $this->unitOrder;
    }

    public function setUnitOrder(int $unitOrder): self
    {
        $this->unitOrder = $unitOrder;
        return $this;
    }

    public function getAvailableLanguages(): ?array
    {
        return $this->availableLanguages;
    }

    public function setAvailableLanguages(?array $availableLanguages): self
    {
        $this->availableLanguages = $availableLanguages;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // Getters and setters ...
}