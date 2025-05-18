<?php

namespace App\Entity;

use App\Repository\ReadingLevelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: ReadingLevelRepository::class)]
class ReadingLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $level = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(name: 'chapter_words')]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $chapterWords = null;

    #[ORM\Column(name: 'words_per_minute', type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $wordsPerMinute = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getChapterWords(): ?int
    {
        return $this->chapterWords;
    }

    public function setChapterWords(int $chapterWords): static
    {
        $this->chapterWords = $chapterWords;
        return $this;
    }

    public function getWordsPerMinute(): ?int
    {
        return $this->wordsPerMinute;
    }

    public function setWordsPerMinute(int $wordsPerMinute): static
    {
        $this->wordsPerMinute = $wordsPerMinute;
        return $this;
    }
}