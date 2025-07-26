<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'news')]
#[ORM\Index(name: 'news_country_idx', columns: ['country'])]
#[ORM\Index(name: 'news_created_at_idx', columns: ['created_at'])]
#[Serializer\ExclusionPolicy('ALL')]
class News
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['news:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Serializer\Expose]
    #[Serializer\Groups(['news:read'])]
    private string $country;

    #[ORM\Column(type: Types::JSON)]
    #[Serializer\Expose]
    #[Serializer\Groups(['news:read'])]
    private array $news = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['news:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['news:read'])]
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

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getNews(): array
    {
        return $this->news;
    }

    public function setNews(array $news): self
    {
        $this->news = $news;
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