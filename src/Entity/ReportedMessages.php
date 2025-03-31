<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reported_messages')]
class ReportedMessages
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'author', referencedColumnName: 'id')]
    private ?Learner $author = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'reporter', referencedColumnName: 'id')]
    private ?Learner $reporter = null;

    #[ORM\Column(type: Types::STRING)]
    private string $messageUid;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getMessageUid(): string
    {
        return $this->messageUid;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getAuthor(): ?Learner
    {
        return $this->author;
    }

    public function setAuthor(Learner $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getReporter(): ?Learner
    {
        return $this->reporter;
    }

    public function setMessageUid(string $messageUid): self
    {
        $this->messageUid = $messageUid;
        return $this;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function setLearner(Learner $learner): self
    {
        $this->learner = $learner;
        return $this;
    }

    public function setReporter(Learner $reporter): self
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }






}