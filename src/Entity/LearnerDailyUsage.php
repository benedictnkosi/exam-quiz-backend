<?php

namespace App\Entity;

use App\Repository\LearnerDailyUsageRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: LearnerDailyUsageRepository::class)]
#[ORM\Table(name: 'learner_daily_usage')]
#[ORM\UniqueConstraint(name: 'learner_date_unique', columns: ['learner', 'date'])]
class LearnerDailyUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Serializer\Groups(['learner_daily_usage:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'learner', referencedColumnName: 'id', nullable: false)]
    #[Serializer\Groups(['learner_daily_usage:read'])]
    private ?Learner $learner = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['learner_daily_usage:read'])]
    private int $quiz = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['learner_daily_usage:read'])]
    private int $lesson = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['learner_daily_usage:read'])]
    private int $podcast = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Serializer\Groups(['learner_daily_usage:read'])]
    private \DateTimeImmutable $date;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
    }

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

    public function getQuiz(): int
    {
        return $this->quiz;
    }

    public function setQuiz(int $quiz): self
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function incrementQuiz(): self
    {
        $this->quiz++;
        return $this;
    }

    public function getLesson(): int
    {
        return $this->lesson;
    }

    public function setLesson(int $lesson): self
    {
        $this->lesson = $lesson;
        return $this;
    }

    public function incrementLesson(): self
    {
        $this->lesson++;
        return $this;
    }

    public function getPodcast(): int
    {
        return $this->podcast;
    }

    public function setPodcast(int $podcast): self
    {
        $this->podcast = $podcast;
        return $this;
    }

    public function incrementPodcast(): self
    {
        $this->podcast++;
        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;
        return $this;
    }
}