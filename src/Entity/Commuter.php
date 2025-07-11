<?php

namespace App\Entity;

use App\Repository\CommuterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CommuterRepository::class)]
#[ORM\Table(name: 'commuters')]
class Commuter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uid;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20)]
    private string $phoneNumber;

    #[ORM\Column(type: 'string', length: 500)]
    private string $homeAddressStreet;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?float $homeLat = null;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 8, nullable: true)]
    private ?float $homeLng = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $homeProvince;

    #[ORM\Column(type: 'string', length: 100)]
    private string $homeCity;

    #[ORM\Column(type: 'string', length: 500)]
    private string $workAddressStreet;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?float $workLat = null;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 8, nullable: true)]
    private ?float $workLng = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $workProvince;

    #[ORM\Column(type: 'string', length: 100)]
    private string $workCity;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $subscription = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $routeCoordinates = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $pushNotificationToken = null;

    public function __construct()
    {
        $this->uid = Uuid::v4()->toRfc4122();
        $this->created = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getHomeAddressStreet(): string
    {
        return $this->homeAddressStreet;
    }

    public function setHomeAddressStreet(string $homeAddressStreet): self
    {
        $this->homeAddressStreet = $homeAddressStreet;
        return $this;
    }

    public function getHomeLat(): ?float
    {
        return $this->homeLat;
    }

    public function setHomeLat(?float $homeLat): self
    {
        $this->homeLat = $homeLat;
        return $this;
    }

    public function getHomeLng(): ?float
    {
        return $this->homeLng;
    }

    public function setHomeLng(?float $homeLng): self
    {
        $this->homeLng = $homeLng;
        return $this;
    }

    public function getHomeProvince(): string
    {
        return $this->homeProvince;
    }

    public function setHomeProvince(string $homeProvince): self
    {
        $this->homeProvince = $homeProvince;
        return $this;
    }

    public function getHomeCity(): string
    {
        return $this->homeCity;
    }

    public function setHomeCity(string $homeCity): self
    {
        $this->homeCity = $homeCity;
        return $this;
    }

    public function getWorkAddressStreet(): string
    {
        return $this->workAddressStreet;
    }

    public function setWorkAddressStreet(string $workAddressStreet): self
    {
        $this->workAddressStreet = $workAddressStreet;
        return $this;
    }

    public function getWorkLat(): ?float
    {
        return $this->workLat;
    }

    public function setWorkLat(?float $workLat): self
    {
        $this->workLat = $workLat;
        return $this;
    }

    public function getWorkLng(): ?float
    {
        return $this->workLng;
    }

    public function setWorkLng(?float $workLng): self
    {
        $this->workLng = $workLng;
        return $this;
    }

    public function getWorkProvince(): string
    {
        return $this->workProvince;
    }

    public function setWorkProvince(string $workProvince): self
    {
        $this->workProvince = $workProvince;
        return $this;
    }

    public function getWorkCity(): string
    {
        return $this->workCity;
    }

    public function setWorkCity(string $workCity): self
    {
        $this->workCity = $workCity;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getSubscription(): ?string
    {
        return $this->subscription;
    }

    public function setSubscription(?string $subscription): self
    {
        $this->subscription = $subscription;
        return $this;
    }

    public function isDriver(): bool
    {
        return $this->type === 'driver';
    }

    public function isPassenger(): bool
    {
        return $this->type === 'passenger';
    }

    public function getRouteCoordinates(): ?array
    {
        return $this->routeCoordinates;
    }

    public function setRouteCoordinates(?array $routeCoordinates): self
    {
        $this->routeCoordinates = $routeCoordinates;
        return $this;
    }

    public function getPushNotificationToken(): ?string
    {
        return $this->pushNotificationToken;
    }

    public function setPushNotificationToken(?string $pushNotificationToken): self
    {
        $this->pushNotificationToken = $pushNotificationToken;
        return $this;
    }
} 