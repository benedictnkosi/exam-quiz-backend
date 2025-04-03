<?php

namespace App\Entity;

use App\Repository\LearnerNoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LearnerNoteRepository::class)]
class LearnerNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['note:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['note:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['note:read'])]
    private ?string $text = null;

    #[ORM\ManyToOne(targetEntity: Learner::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Learner $learner = null;

    #[ORM\Column(length: 255)]
    #[Groups(['note:read'])]
    private ?string $subjectName = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
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

    public function getSubjectName(): ?string
    {
        return $this->subjectName;
    }

    public function setSubjectName(string $subjectName): static
    {
        $this->subjectName = $subjectName;
        return $this;
    }
} 