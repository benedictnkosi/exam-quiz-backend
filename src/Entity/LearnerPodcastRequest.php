<?php

namespace App\Entity;

use App\Repository\LearnerPodcastRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LearnerPodcastRequestRepository::class)]
class LearnerPodcastRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'podcastRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Learner $learner = null;

    #[ORM\Column(length: 255)]
    private ?string $podcastFileId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $requestedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLearner(): ?Learner
    {
        return $this->learner;
    }

    public function setLearner(?Learner $learner): static
    {
        $this->learner = $learner;
        return $this;
    }

    public function getPodcastFileId(): ?string
    {
        return $this->podcastFileId;
    }

    public function setPodcastFileId(string $podcastFileId): static
    {
        $this->podcastFileId = $podcastFileId;
        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;
        return $this;
    }
}