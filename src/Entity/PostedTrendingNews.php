<?php

namespace App\Entity;

use App\Repository\PostedTrendingNewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: PostedTrendingNewsRepository::class)]
#[ORM\Table(name: 'posted_trending_news')]
#[ORM\Index(name: 'posted_trending_scope_idx', columns: ['scope'])]
#[ORM\Index(name: 'posted_trending_politician_idx', columns: ['politician_name'])]
#[ORM\Index(name: 'posted_trending_posted_at_idx', columns: ['posted_at'])]
#[ORM\Index(name: 'posted_trending_tweet_id_idx', columns: ['tweet_id'])]
#[Serializer\ExclusionPolicy('ALL')]
class PostedTrendingNews
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private string $scope;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private ?string $politicianName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private string $twitterSummary;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private ?string $tweetId = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private int $characterCount;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    private ?array $originalStory = null;

    #[ORM\Column(name: 'posted_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private \DateTime $postedAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['posted_trending:read'])]
    #[Serializer\Type('DateTime<"c">')]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->postedAt = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getPoliticianName(): ?string
    {
        return $this->politicianName;
    }

    public function setPoliticianName(?string $politicianName): self
    {
        $this->politicianName = $politicianName;
        return $this;
    }

    public function getTwitterSummary(): string
    {
        return $this->twitterSummary;
    }

    public function setTwitterSummary(string $twitterSummary): self
    {
        $this->twitterSummary = $twitterSummary;
        return $this;
    }

    public function getTweetId(): ?string
    {
        return $this->tweetId;
    }

    public function setTweetId(?string $tweetId): self
    {
        $this->tweetId = $tweetId;
        return $this;
    }

    public function getCharacterCount(): int
    {
        return $this->characterCount;
    }

    public function setCharacterCount(int $characterCount): self
    {
        $this->characterCount = $characterCount;
        return $this;
    }

    public function getOriginalStory(): ?array
    {
        return $this->originalStory;
    }

    public function setOriginalStory(?array $originalStory): self
    {
        $this->originalStory = $originalStory;
        return $this;
    }

    public function getPostedAt(): \DateTime
    {
        return $this->postedAt;
    }

    public function setPostedAt(\DateTime $postedAt): self
    {
        $this->postedAt = $postedAt;
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