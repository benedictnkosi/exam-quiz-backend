<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'todo')]
class Todo
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Groups(['todo'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(['todo'])]
    private ?string $title = null;

    #[ORM\Column(length: 20)]
    #[Serializer\Groups(['todo'])]
    private ?string $status = null;

    #[ORM\Column]
    #[Serializer\Groups(['todo'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Serializer\Groups(['todo'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\ManyToOne(inversedBy: 'todos')]
    #[ORM\JoinColumn(nullable: false)]
    #[Serializer\Exclude]
    private ?Learner $learner = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'pending';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

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