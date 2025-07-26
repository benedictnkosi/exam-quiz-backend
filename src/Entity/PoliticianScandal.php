<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'politician_scandals')]
#[ORM\Index(name: 'scandal_politician_idx', columns: ['politician'])]
#[ORM\Index(name: 'scandal_country_idx', columns: ['country'])]
#[ORM\Index(name: 'scandal_created_at_idx', columns: ['created_at'])]
#[Serializer\ExclusionPolicy('ALL')]
class PoliticianScandal
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    private string $politician;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    private string $country;

    #[ORM\Column(type: Types::JSON)]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    private array $scandals = [];

    #[ORM\Column(name: 'total_corruption_score', type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    private int $totalCorruptionScore = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['scandal:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPolitician(): string
    {
        return $this->politician;
    }

    public function setPolitician(string $politician): self
    {
        $this->politician = $politician;
        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getScandals(): array
    {
        return $this->scandals;
    }

    public function setScandals(array $scandals): self
    {
        $this->scandals = $scandals;
        return $this;
    }

    public function getTotalCorruptionScore(): int
    {
        return $this->totalCorruptionScore;
    }

    public function setTotalCorruptionScore(int $totalCorruptionScore): self
    {
        $this->totalCorruptionScore = $totalCorruptionScore;
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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
} 