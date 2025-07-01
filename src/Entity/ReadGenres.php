<?php

namespace App\Entity;

use App\Repository\ReadGenresRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: ReadGenresRepository::class)]
#[ORM\Table(name: 'read_genres')]
#[Serializer\ExclusionPolicy('none')]
class ReadGenres
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $genreName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $subcategories = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Serializer\Type('DateTime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Type('DateTime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Serializer\Type('boolean')]
    private bool $isActive = true;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGenreName(): ?string
    {
        return $this->genreName;
    }

    public function setGenreName(?string $genreName): static
    {
        $this->genreName = $genreName;

        return $this;
    }

    public function getSubcategories(): ?array
    {
        return $this->subcategories;
    }

    public function setSubcategories(?array $subcategories): static
    {
        $this->subcategories = $subcategories;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
} 