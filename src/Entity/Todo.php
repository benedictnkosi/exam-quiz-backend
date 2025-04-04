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

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serializer\Groups(['todo'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Groups(['todo'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Serializer\Groups(['todo'])]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Serializer\Groups(['todo'])]
    private \DateTime $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Groups(['todo'])]
    private ?\DateTime $dueDate = null;

    #[ORM\ManyToOne(targetEntity: Learner::class, inversedBy: 'todos')]
    #[ORM\JoinColumn(nullable: false)]
    #[Serializer\Exclude]
    private Learner $learner;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTime $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
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
} 