<?php

namespace App\Entity;

use App\Repository\DriverPassengerDistanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DriverPassengerDistanceRepository::class)]
#[ORM\Table(name: 'commute_distances')]
#[ORM\Index(columns: ['driver_uid', 'passenger_uid'], name: 'driver_passenger_idx')]
#[ORM\Index(columns: ['distance'], name: 'distance_idx')]
#[ORM\Index(columns: ['calculated_at'], name: 'calculated_at_idx')]
class DriverPassengerDistance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'integer')]
    private int $driverCommuteId;

    #[ORM\Column(type: 'integer')]
    private int $passengerCommuteId;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $homeDistance = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $workDistance = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $maxDistance = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $calculatedAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->calculatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDriverCommuteId(): int
    {
        return $this->driverCommuteId;
    }

    public function setDriverCommuteId(int $driverCommuteId): static
    {
        $this->driverCommuteId = $driverCommuteId;
        return $this;
    }

    public function getPassengerCommuteId(): int
    {
        return $this->passengerCommuteId;
    }

    public function setPassengerCommuteId(int $passengerCommuteId): static
    {
        $this->passengerCommuteId = $passengerCommuteId;
        return $this;
    }

    public function getHomeDistance(): ?float
    {
        return $this->homeDistance;
    }

    public function setHomeDistance(float $homeDistance): static
    {
        $this->homeDistance = $homeDistance;
        return $this;
    }

    public function getWorkDistance(): ?float
    {
        return $this->workDistance;
    }

    public function setWorkDistance(float $workDistance): static
    {
        $this->workDistance = $workDistance;
        return $this;
    }

    public function getMaxDistance(): ?float
    {
        return $this->maxDistance;
    }

    public function setMaxDistance(float $maxDistance): static
    {
        $this->maxDistance = $maxDistance;
        return $this;
    }

    public function getCalculatedAt(): ?\DateTimeInterface
    {
        return $this->calculatedAt;
    }

    public function setCalculatedAt(\DateTimeInterface $calculatedAt): static
    {
        $this->calculatedAt = $calculatedAt;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
} 