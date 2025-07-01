<?php

namespace App\Entity;

use App\Repository\DeviceRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceRegistrationRepository::class)]
class DeviceRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $deviceId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $learnerUid = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $registrationDate = null;

    public function __construct()
    {
        $this->registrationDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): self
    {
        $this->deviceId = $deviceId;
        return $this;
    }

    public function getLearnerUid(): ?string
    {
        return $this->learnerUid;
    }

    public function setLearnerUid(?string $learnerUid): self
    {
        $this->learnerUid = $learnerUid;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeImmutable
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(\DateTimeImmutable $registrationDate): self
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }
} 