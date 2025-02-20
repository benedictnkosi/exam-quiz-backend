<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class LearnerStreak
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Learner $learner = null;

    #[ORM\Column]
    private int $currentStreak = 0;

    #[ORM\Column]
    private int $longestStreak = 0;

    #[ORM\Column]
    private int $questionsAnsweredToday = 0;

    #[ORM\Column]
    private \DateTime $lastAnsweredAt;

    #[ORM\Column]
    private \DateTime $lastStreakUpdateDate;

    public function __construct()
    {
        $this->lastAnsweredAt = new \DateTime();
        $this->lastStreakUpdateDate = new \DateTime();
    }

    // Getters and setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurrentStreak(): ?int
    {
        return $this->currentStreak;
    }

    public function setCurrentStreak(int $currentStreak): static
    {
        $this->currentStreak = $currentStreak;

        return $this;
    }

    public function getLongestStreak(): ?int
    {
        return $this->longestStreak;
    }

    public function setLongestStreak(int $longestStreak): static
    {
        $this->longestStreak = $longestStreak;

        return $this;
    }

    public function getQuestionsAnsweredToday(): ?int
    {
        return $this->questionsAnsweredToday;
    }

    public function setQuestionsAnsweredToday(int $questionsAnsweredToday): static
    {
        $this->questionsAnsweredToday = $questionsAnsweredToday;

        return $this;
    }

    public function getLastAnsweredAt(): ?\DateTimeInterface
    {
        return $this->lastAnsweredAt;
    }

    public function setLastAnsweredAt(\DateTimeInterface $lastAnsweredAt): static
    {
        $this->lastAnsweredAt = $lastAnsweredAt;

        return $this;
    }

    public function getLastStreakUpdateDate(): ?\DateTimeInterface
    {
        return $this->lastStreakUpdateDate;
    }

    public function setLastStreakUpdateDate(\DateTimeInterface $lastStreakUpdateDate): static
    {
        $this->lastStreakUpdateDate = $lastStreakUpdateDate;

        return $this;
    }

    public function getLearner(): ?Learner
    {
        return $this->learner;
    }

    public function setLearner(?Learner $learner): static
    {
        $this->learner = $learner;

        return $this;
    }
} 