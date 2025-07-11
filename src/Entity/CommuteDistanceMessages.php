<?php

namespace App\Entity;

use App\Repository\CommuteDistanceMessagesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommuteDistanceMessagesRepository::class)]
#[ORM\Table(name: 'commute_distance_messages')]
#[ORM\Index(columns: ['commute_distance_id'], name: 'commute_distance_idx')]
#[ORM\Index(columns: ['passenger_last_message_date'], name: 'passenger_message_date_idx')]
#[ORM\Index(columns: ['driver_last_message_date'], name: 'driver_message_date_idx')]
class CommuteDistanceMessages
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'integer')]
    private int $commuteDistanceId;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $passengerLastMessageDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $driverLastMessageDate = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommuteDistanceId(): int
    {
        return $this->commuteDistanceId;
    }

    public function setCommuteDistanceId(int $commuteDistanceId): static
    {
        $this->commuteDistanceId = $commuteDistanceId;
        return $this;
    }

    public function getPassengerLastMessageDate(): ?\DateTimeInterface
    {
        return $this->passengerLastMessageDate;
    }

    public function setPassengerLastMessageDate(?\DateTimeInterface $passengerLastMessageDate): static
    {
        $this->passengerLastMessageDate = $passengerLastMessageDate;
        return $this;
    }

    public function getDriverLastMessageDate(): ?\DateTimeInterface
    {
        return $this->driverLastMessageDate;
    }

    public function setDriverLastMessageDate(?\DateTimeInterface $driverLastMessageDate): static
    {
        $this->driverLastMessageDate = $driverLastMessageDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
} 