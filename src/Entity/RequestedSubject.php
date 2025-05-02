<?php

namespace App\Entity;

use App\Repository\RequestedSubjectRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestedSubjectRepository::class)]
class RequestedSubject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Learner $requester = null;

    #[ORM\Column(length: 255)]
    private ?string $subjectName = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $requestDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): ?Learner
    {
        return $this->requester;
    }

    public function setRequester(?Learner $requester): self
    {
        $this->requester = $requester;
        return $this;
    }

    public function getSubjectName(): ?string
    {
        return $this->subjectName;
    }

    public function setSubjectName(string $subjectName): self
    {
        $this->subjectName = $subjectName;
        return $this;
    }

    public function getRequestDate(): ?\DateTimeInterface
    {
        return $this->requestDate;
    }

    public function setRequestDate(\DateTimeInterface $requestDate): self
    {
        $this->requestDate = $requestDate;
        return $this;
    }
}