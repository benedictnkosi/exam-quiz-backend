<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'language_unit')]
class Languages
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 10, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $nativeName;

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getNativeName(): string
    {
        return $this->nativeName;
    }

    public function setNativeName(string $nativeName): self
    {
        $this->nativeName = $nativeName;
        return $this;
    }
}