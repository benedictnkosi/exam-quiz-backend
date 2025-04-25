<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'subject')]
#[ORM\Index(name: 'subject_grade_idx', columns: ['grade'])]
class Subject
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $examDate = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $topics = null;

    #[ORM\ManyToOne(targetEntity: Grade::class)]
    #[ORM\JoinColumn(name: 'grade', referencedColumnName: 'id')]
    private ?Grade $grade = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'capturer', referencedColumnName: 'id')]
    private ?Learner $capturer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getCapturer(): ?Learner
    {
        return $this->capturer;
    }

    public function setCapturer(?Learner $capturer): static
    {
        $this->capturer = $capturer;

        return $this;
    }

    public function getExamDate(): ?\DateTimeInterface
    {
        return $this->examDate;
    }

    public function setExamDate(?\DateTimeInterface $examDate): static
    {
        $this->examDate = $examDate;

        return $this;
    }

    public function getTopics(): ?array
    {
        return $this->topics;
    }

    public function setTopics(?array $topics): static
    {
        $this->topics = $topics;

        return $this;
    }
}
