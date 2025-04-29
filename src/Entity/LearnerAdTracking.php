<?php

namespace App\Entity;

use App\Repository\LearnerAdTrackingRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: LearnerAdTrackingRepository::class)]
#[ORM\Table(name: 'learner_ad_tracking')]
class LearnerAdTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Serializer\Groups(['learner_ad_tracking:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'learner', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Serializer\Groups(['learner_ad_tracking:read'])]
    private ?Learner $learner = null;

    #[ORM\Column]
    #[Serializer\Groups(['learner_ad_tracking:read'])]
    private int $questionsAnswered = 0;

    #[ORM\Column]
    #[Serializer\Groups(['learner_ad_tracking:read'])]
    private int $adsShown = 0;

    #[ORM\Column(nullable: true)]
    #[Serializer\Groups(['learner_ad_tracking:read'])]
    private ?\DateTime $lastAdShownAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getQuestionsAnswered(): int
    {
        return $this->questionsAnswered;
    }

    public function setQuestionsAnswered(int $questionsAnswered): self
    {
        $this->questionsAnswered = $questionsAnswered;
        return $this;
    }

    public function incrementQuestionsAnswered(): self
    {
        $this->questionsAnswered++;
        return $this;
    }

    public function getAdsShown(): int
    {
        return $this->adsShown;
    }

    public function setAdsShown(int $adsShown): self
    {
        $this->adsShown = $adsShown;
        return $this;
    }

    public function incrementAdsShown(): self
    {
        $this->adsShown++;
        $this->lastAdShownAt = new \DateTime();
        return $this;
    }

    public function getLastAdShownAt(): ?\DateTime
    {
        return $this->lastAdShownAt;
    }

    public function setLastAdShownAt(?\DateTime $lastAdShownAt): self
    {
        $this->lastAdShownAt = $lastAdShownAt;
        return $this;
    }
}