<?php

namespace App\Entity;

use App\Repository\StoryArcRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StoryArcRepository::class)]
class StoryArc
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $theme = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $goal = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull]
    private ?\DateTimeInterface $publishDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $chapterName = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $chapterNumber = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $outline = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_NEW, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_ARCHIVED])]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getGoal(): ?string
    {
        return $this->goal;
    }

    public function setGoal(string $goal): static
    {
        $this->goal = $goal;
        return $this;
    }

    public function getPublishDate(): ?\DateTimeInterface
    {
        return $this->publishDate;
    }

    public function setPublishDate(\DateTimeInterface $publishDate): static
    {
        $this->publishDate = $publishDate;
        return $this;
    }

    public function getChapterName(): ?string
    {
        return $this->chapterName;
    }

    public function setChapterName(string $chapterName): static
    {
        $this->chapterName = $chapterName;
        return $this;
    }

    public function getChapterNumber(): ?int
    {
        return $this->chapterNumber;
    }

    public function setChapterNumber(int $chapterNumber): static
    {
        $this->chapterNumber = $chapterNumber;
        return $this;
    }

    public function getOutline(): ?string
    {
        return $this->outline;
    }

    public function setOutline(string $outline): static
    {
        $this->outline = $outline;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }
}