<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'restaurant_menu')]
#[ORM\UniqueConstraint(name: 'unique_restaurant_foodtype', columns: ['restaurant_name', 'food_type'])]
class RestaurantMenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'restaurant_name', type: 'string', length: 255)]
    private string $restaurantName;

    #[ORM\Column(name: 'food_type', type: 'string', length: 100)]
    private string $foodType;

    #[ORM\Column(name: 'menu_result', type: 'json')]
    private array $menuResult = [];

    #[ORM\Column(name: 'status', type: 'string', length: 50, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: 'request_id', type: 'string', length: 64, nullable: true)]
    private ?string $requestId = null;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'address', type: 'string', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'rating', type: 'float', nullable: true)]
    private ?float $rating = null;

    #[ORM\Column(name: 'user_ratings_total', type: 'integer', nullable: true)]
    private ?int $userRatingsTotal = null;

    #[ORM\Column(name: 'price_level', type: 'integer', nullable: true)]
    private ?int $priceLevel = null;

    #[ORM\Column(name: 'place_id', type: 'string', length: 128, nullable: true)]
    private ?string $placeId = null;

    #[ORM\Column(name: 'types', type: 'json', nullable: true)]
    private ?array $types = null;

    #[ORM\Column(name: 'menu_prices', type: 'json', nullable: true)]
    private ?array $menuPrices = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRestaurantName(): string
    {
        return $this->restaurantName;
    }

    public function setRestaurantName(string $restaurantName): self
    {
        $this->restaurantName = $restaurantName;
        return $this;
    }

    public function getFoodType(): string
    {
        return $this->foodType;
    }

    public function setFoodType(string $foodType): self
    {
        $this->foodType = $foodType;
        return $this;
    }

    public function getMenuResult(): array
    {
        return $this->menuResult;
    }

    public function setMenuResult(array $menuResult): self
    {
        $this->menuResult = $menuResult;
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

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }
    public function getRating(): ?float
    {
        return $this->rating;
    }
    public function setRating(?float $rating): self
    {
        $this->rating = $rating;
        return $this;
    }
    public function getUserRatingsTotal(): ?int
    {
        return $this->userRatingsTotal;
    }
    public function setUserRatingsTotal(?int $userRatingsTotal): self
    {
        $this->userRatingsTotal = $userRatingsTotal;
        return $this;
    }
    public function getPriceLevel(): ?int
    {
        return $this->priceLevel;
    }
    public function setPriceLevel(?int $priceLevel): self
    {
        $this->priceLevel = $priceLevel;
        return $this;
    }
    public function getPlaceId(): ?string
    {
        return $this->placeId;
    }
    public function setPlaceId(?string $placeId): self
    {
        $this->placeId = $placeId;
        return $this;
    }
    public function getTypes(): ?array
    {
        return $this->types;
    }
    public function setTypes(?array $types): self
    {
        $this->types = $types;
        return $this;
    }

    public function getMenuPrices(): ?array
    {
        return $this->menuPrices;
    }
    public function setMenuPrices(?array $menuPrices): self
    {
        $this->menuPrices = $menuPrices;
        return $this;
    }
} 