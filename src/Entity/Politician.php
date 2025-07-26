<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'politicians')]
#[ORM\Index(name: 'politician_country_idx', columns: ['country'])]
#[ORM\Index(name: 'politician_trending_idx', columns: ['trending'])]
#[ORM\Index(name: 'politician_created_at_idx', columns: ['created_at'])]
#[Serializer\ExclusionPolicy('ALL')]
class Politician
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'full_name', type: Types::STRING, length: 200)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private string $fullName;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private string $country;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private ?string $party = null;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private ?string $position = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private int $score = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'active'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private string $status = 'active';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private ?string $note = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    private bool $trending = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['politician:read'])]
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

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;
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

    public function getParty(): ?string
    {
        return $this->party;
    }

    public function setParty(?string $party): self
    {
        $this->party = $party;
        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function isTrending(): bool
    {
        return $this->trending;
    }

    public function setTrending(bool $trending): self
    {
        $this->trending = $trending;
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