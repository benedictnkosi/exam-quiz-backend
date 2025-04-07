<?php

namespace App\Entity;

use App\Repository\SmsMarketingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SmsMarketingRepository::class)]
class SmsMarketing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $phoneNumber = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSmsSentAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastMessageSent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastMessageId = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSmsSentAt(): ?\DateTimeImmutable
    {
        return $this->lastSmsSentAt;
    }

    public function setLastSmsSentAt(?\DateTimeImmutable $lastSmsSentAt): static
    {
        $this->lastSmsSentAt = $lastSmsSentAt;
        return $this;
    }

    public function getLastMessageSent(): ?string
    {
        return $this->lastMessageSent;
    }

    public function setLastMessageSent(?string $lastMessageSent): static
    {
        $this->lastMessageSent = $lastMessageSent;
        return $this;
    }

    public function getLastMessageId(): ?string
    {
        return $this->lastMessageId;
    }

    public function setLastMessageId(?string $lastMessageId): static
    {
        $this->lastMessageId = $lastMessageId;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}