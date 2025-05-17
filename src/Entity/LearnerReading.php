<?php

namespace App\Entity;

use App\Repository\LearnerReadingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LearnerReadingRepository::class)]
class LearnerReading
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $date;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Book $chapter;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $status;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Learner $learner;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\NotNull]
    #[Assert\Type(type: 'integer')]
    private int $duration = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\NotNull]
    #[Assert\Type(type: 'integer')]
    private int $score = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\NotNull]
    #[Assert\Type(type: 'integer')]
    private int $speed = 0;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getChapter(): Book
    {
        return $this->chapter;
    }

    public function setChapter(Book $chapter): static
    {
        $this->chapter = $chapter;
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

    public function getLearner(): Learner
    {
        return $this->learner;
    }

    public function setLearner(Learner $learner): static
    {
        $this->learner = $learner;
        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getSpeed(): int
    {
        return $this->speed;
    }

    public function setSpeed(int $speed): static
    {
        $this->speed = $speed;
        return $this;
    }
}