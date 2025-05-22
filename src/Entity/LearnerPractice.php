<?php

namespace App\Entity;

use App\Repository\LearnerPracticeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LearnerPracticeRepository::class)]
#[ORM\Table(name: 'learner_practice')]
class LearnerPractice
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $subject_name;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'learner', referencedColumnName: 'id')]
    private Learner $learner;

    #[ORM\Column(type: Types::JSON)]
    private array $progress = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $last_seen;

    public function __construct()
    {
        $this->last_seen = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectName(): string
    {
        return $this->subject_name;
    }

    public function setSubjectName(string $subject_name): static
    {
        $this->subject_name = $subject_name;
        return $this;
    }

    public function getLearner(): Learner
    {
        return $this->learner;
    }

    public function setLearner(Learner $learner): static
    {
        $this->learner = $learner;
        return $this;
    }

    public function getProgress(): array
    {
        return $this->progress;
    }

    public function setProgress(array $progress): static
    {
        $this->progress = $progress;
        return $this;
    }

    public function getLastSeen(): \DateTime
    {
        return $this->last_seen;
    }

    public function setLastSeen(\DateTime $last_seen): static
    {
        $this->last_seen = $last_seen;
        return $this;
    }
}