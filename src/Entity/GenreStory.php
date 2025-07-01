<?php

namespace App\Entity;

use App\Repository\GenreStoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: GenreStoryRepository::class)]
#[ORM\Table(name: 'genre_story')]
#[Serializer\ExclusionPolicy('none')]
class GenreStory
{
    public const AGE_GROUP_7_10 = '7-10';
    public const AGE_GROUP_11_14 = '11-14';
    public const AGE_GROUP_15_18 = '15-18';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GenrePlot::class)]
    #[ORM\JoinColumn(name: 'plot_id', referencedColumnName: 'id', nullable: false)]
    #[Serializer\MaxDepth(1)]
    private ?GenrePlot $plot = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $ageGroup = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    #[Serializer\Type('integer')]
    private ?int $chapterNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $accumulativeSummary = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serializer\Type('integer')]
    private ?int $wordCount = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serializer\Type('integer')]
    private ?int $readingTime = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $vocabulary = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $quiz = null;

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

    public function getPlot(): ?GenrePlot
    {
        return $this->plot;
    }

    public function setPlot(?GenrePlot $plot): static
    {
        $this->plot = $plot;
        return $this;
    }

    public function getAgeGroup(): ?string
    {
        return $this->ageGroup;
    }

    public function setAgeGroup(?string $ageGroup): static
    {
        $this->ageGroup = $ageGroup;
        return $this;
    }

    public function getChapterNumber(): ?int
    {
        return $this->chapterNumber;
    }

    public function setChapterNumber(?int $chapterNumber): static
    {
        $this->chapterNumber = $chapterNumber;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getAccumulativeSummary(): ?string
    {
        return $this->accumulativeSummary;
    }

    public function setAccumulativeSummary(?string $accumulativeSummary): static
    {
        $this->accumulativeSummary = $accumulativeSummary;
        return $this;
    }

    public function getWordCount(): ?int
    {
        return $this->wordCount;
    }

    public function setWordCount(?int $wordCount): static
    {
        $this->wordCount = $wordCount;
        return $this;
    }

    public function getReadingTime(): ?int
    {
        return $this->readingTime;
    }

    public function setReadingTime(?int $readingTime): static
    {
        $this->readingTime = $readingTime;
        return $this;
    }

    public function getVocabulary(): ?array
    {
        return $this->vocabulary;
    }

    public function setVocabulary(?array $vocabulary): static
    {
        $this->vocabulary = $vocabulary;
        return $this;
    }

    public function getQuiz(): ?array
    {
        return $this->quiz;
    }

    public function setQuiz(?array $quiz): static
    {
        $this->quiz = $quiz;
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

    public static function getAgeGroups(): array
    {
        return [
            self::AGE_GROUP_7_10,
            self::AGE_GROUP_11_14,
            self::AGE_GROUP_15_18
        ];
    }
} 