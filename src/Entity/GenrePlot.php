<?php

namespace App\Entity;

use App\Repository\GenrePlotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: GenrePlotRepository::class)]
#[ORM\Table(name: 'genre_plot')]
#[Serializer\ExclusionPolicy('none')]
class GenrePlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ReadGenres::class)]
    #[ORM\JoinColumn(name: 'genre_id', referencedColumnName: 'id', nullable: false)]
    #[Serializer\MaxDepth(1)]
    private ?ReadGenres $genre = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $subcategory = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $synopsis = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $conflict = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $characters = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $setting = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $hook = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
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

    public function getGenre(): ?ReadGenres
    {
        return $this->genre;
    }

    public function setGenre(?ReadGenres $genre): static
    {
        $this->genre = $genre;
        return $this;
    }

    public function getSubcategory(): ?string
    {
        return $this->subcategory;
    }

    public function setSubcategory(?string $subcategory): static
    {
        $this->subcategory = $subcategory;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): static
    {
        $this->synopsis = $synopsis;
        return $this;
    }

    public function getConflict(): ?string
    {
        return $this->conflict;
    }

    public function setConflict(?string $conflict): static
    {
        $this->conflict = $conflict;
        return $this;
    }

    public function getCharacters(): ?string
    {
        return $this->characters;
    }

    public function setCharacters(?string $characters): static
    {
        $this->characters = $characters;
        return $this;
    }

    public function getSetting(): ?string
    {
        return $this->setting;
    }

    public function setSetting(?string $setting): static
    {
        $this->setting = $setting;
        return $this;
    }

    public function getHook(): ?string
    {
        return $this->hook;
    }

    public function setHook(?string $hook): static
    {
        $this->hook = $hook;
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