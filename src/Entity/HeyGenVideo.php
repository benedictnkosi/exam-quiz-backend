<?php

namespace App\Entity;

use App\Repository\HeyGenVideoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeyGenVideoRepository::class)]
#[ORM\Table(name: 'heygen_videos')]
class HeyGenVideo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $videoUrl;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $captionUrl = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $uploaded = false;

    public function __construct(string $title, string $videoUrl, bool $uploaded = false, ?string $captionUrl = null)
    {
        $this->title = $title;
        $this->videoUrl = $videoUrl;
        $this->uploaded = $uploaded;
        $this->captionUrl = $captionUrl;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getVideoUrl(): string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(string $videoUrl): void
    {
        $this->videoUrl = $videoUrl;
    }

    public function getCaptionUrl(): ?string
    {
        return $this->captionUrl;
    }

    public function setCaptionUrl(?string $captionUrl): void
    {
        $this->captionUrl = $captionUrl;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isUploaded(): bool
    {
        return $this->uploaded;
    }

    public function setUploaded(bool $uploaded): void
    {
        $this->uploaded = $uploaded;
    }
}


