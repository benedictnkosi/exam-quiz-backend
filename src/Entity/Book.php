<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StoryArc::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?StoryArc $storyArc = null;

    #[ORM\ManyToOne(targetEntity: ReadingLevel::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReadingLevel $readingLevel = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $chapterName = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $chapterNumber = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $content = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $summary = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $wordCount = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $level = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_ACTIVE, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $quiz = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $publishDate = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function __construct()
    {
        $this->created = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStoryArc(): ?StoryArc
    {
        return $this->storyArc;
    }

    public function setStoryArc(?StoryArc $storyArc): static
    {
        $this->storyArc = $storyArc;
        return $this;
    }

    public function getReadingLevel(): ?ReadingLevel
    {
        return $this->readingLevel;
    }

    public function setReadingLevel(?ReadingLevel $readingLevel): static
    {
        $this->readingLevel = $readingLevel;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getWordCount(): ?int
    {
        return $this->wordCount;
    }

    public function setWordCount(int $wordCount): static
    {
        $this->wordCount = $wordCount;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
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

    public function getQuiz(): ?string
    {
        return $this->quiz;
    }

    public function setQuiz(?string $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getPublishDate(): ?\DateTimeInterface
    {
        return $this->publishDate;
    }

    public function setPublishDate(?\DateTimeInterface $publishDate): static
    {
        $this->publishDate = $publishDate;
        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
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