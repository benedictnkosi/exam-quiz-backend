<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'topic')]
#[Serializer\ExclusionPolicy('none')]
class Topic
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $subTopic = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(name: 'subject_id', referencedColumnName: 'id')]
    #[Serializer\MaxDepth(1)]
    private ?Subject $subject = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $recordingFileName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Type('DateTime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $lecture = null;

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

    public function getSubTopic(): ?string
    {
        return $this->subTopic;
    }

    public function setSubTopic(?string $subTopic): static
    {
        $this->subTopic = $subTopic;

        return $this;
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

    public function getRecordingFileName(): ?string
    {
        return $this->recordingFileName;
    }

    public function setRecordingFileName(?string $recordingFileName): static
    {
        $this->recordingFileName = $recordingFileName;

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

    public function getLecture(): ?string
    {
        return $this->lecture;
    }

    public function setLecture(?string $lecture): static
    {
        $this->lecture = $lecture;

        return $this;
    }
}