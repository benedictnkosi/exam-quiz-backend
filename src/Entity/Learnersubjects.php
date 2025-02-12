<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'learnersubjects')]
#[ORM\Index(name: 'learnersubject_learner_idx', columns: ['learner'])]
#[ORM\Index(name: 'learnersubject_subject_idx', columns: ['subject'])]
class Learnersubjects
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'higherGrade', type: Types::BOOLEAN, nullable: true)]
    private ?bool $highergrade = false;

    #[ORM\Column(name: 'overideTerm', type: Types::BOOLEAN, nullable: true)]
    private ?bool $overideterm = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastUpdated = null;

    #[ORM\Column(type: Types::FLOAT, precision: 10, scale: 0, nullable: true)]
    private ?float $percentage = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'learner', referencedColumnName: 'id')]
    private ?Learner $learner = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(name: 'subject', referencedColumnName: 'id')]
    private ?Subject $subject = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isHighergrade(): ?bool
    {
        return $this->highergrade;
    }

    public function setHighergrade(?bool $highergrade): static
    {
        $this->highergrade = $highergrade;

        return $this;
    }

    public function isOverideterm(): ?bool
    {
        return $this->overideterm;
    }

    public function setOverideterm(?bool $overideterm): static
    {
        $this->overideterm = $overideterm;

        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?\DateTimeInterface $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function setPercentage(?float $percentage): static
    {
        $this->percentage = $percentage;

        return $this;
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

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;

        return $this;
    }
}
