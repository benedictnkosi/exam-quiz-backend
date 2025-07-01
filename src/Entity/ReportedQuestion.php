<?php

namespace App\Entity;

use App\Repository\ReportedQuestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: ReportedQuestionRepository::class)]
#[ORM\Table(name: 'reported_question')]
#[Serializer\ExclusionPolicy('none')]
class ReportedQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(name: 'subject_id', referencedColumnName: 'id')]
    #[Serializer\MaxDepth(1)]
    private ?Subject $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $questionText = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $questionTopic = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $subTopic = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Type('DateTime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Type('DateTime')]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getQuestionText(): ?string
    {
        return $this->questionText;
    }

    public function setQuestionText(?string $questionText): static
    {
        $this->questionText = $questionText;
        return $this;
    }

    public function getQuestionTopic(): ?string
    {
        return $this->questionTopic;
    }

    public function setQuestionTopic(?string $questionTopic): static
    {
        $this->questionTopic = $questionTopic;
        return $this;
    }

    public function getSubTopic(): ?string
    {
        return $this->subTopic;
    }

    public function setSubTopic(?string $subTopic): static
    {
        $this->subTopic = $subTopic;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
} 