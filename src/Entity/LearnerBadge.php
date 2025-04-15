<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'learner_badges')]
class LearnerBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Groups(['learner:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Groups(['learner:read'])]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: Learner::class, inversedBy: 'learnerBadges')]
    #[ORM\JoinColumn(name: 'learner', referencedColumnName: 'id')]
    #[Serializer\Exclude]
    private ?Learner $learner = null;

    #[ORM\ManyToOne(targetEntity: Badge::class, inversedBy: 'learnerBadges')]
    #[ORM\JoinColumn(name: 'badge', referencedColumnName: 'id')]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\MaxDepth(1)]
    private ?Badge $badge = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLearner(): ?Learner
    {
        return $this->learner;
    }

    public function setLearner(?Learner $learner): self
    {
        $this->learner = $learner;
        return $this;
    }

    public function getBadge(): ?Badge
    {
        return $this->badge;
    }

    public function setBadge(?Badge $badge): self
    {
        $this->badge = $badge;
        return $this;
    }
}