<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'learner_maths_practice_stat')]
class LearnerMathsPracticeStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Learner $learner;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Question $question;

    #[ORM\Column(type: 'integer')]
    private int $correct = 0;

    #[ORM\Column(type: 'integer')]
    private int $incorrect = 0;

    #[ORM\Column(type: 'integer')]
    private int $correct_steps = 0;

    #[ORM\Column(type: 'integer')]
    private int $incorrect_steps = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $created;

    public function __construct()
    {
        $this->created = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLearner(): Learner
    {
        return $this->learner;
    }

    public function setLearner(Learner $learner): self
    {
        $this->learner = $learner;
        return $this;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function setQuestion(Question $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function getCorrect(): int
    {
        return $this->correct;
    }

    public function setCorrect(int $correct): self
    {
        $this->correct = $correct;
        return $this;
    }

    public function incrementCorrect(): self
    {
        $this->correct++;
        return $this;
    }

    public function getIncorrect(): int
    {
        return $this->incorrect;
    }

    public function setIncorrect(int $incorrect): self
    {
        $this->incorrect = $incorrect;
        return $this;
    }

    public function incrementIncorrect(): self
    {
        $this->incorrect++;
        return $this;
    }

    public function getCorrectSteps(): int
    {
        return $this->correct_steps;
    }

    public function setCorrectSteps(int $correctSteps): self
    {
        $this->correct_steps = $correctSteps;
        return $this;
    }

    public function incrementCorrectSteps(int $by = 1): self
    {
        $this->correct_steps += $by;
        return $this;
    }

    public function getIncorrectSteps(): int
    {
        return $this->incorrect_steps;
    }

    public function setIncorrectSteps(int $incorrectSteps): self
    {
        $this->incorrect_steps = $incorrectSteps;
        return $this;
    }

    public function incrementIncorrectSteps(int $by = 1): self
    {
        $this->incorrect_steps += $by;
        return $this;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }
} 