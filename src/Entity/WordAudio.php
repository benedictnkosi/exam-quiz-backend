<?php

namespace App\Entity;

use App\Repository\WordAudioRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: WordAudioRepository::class)]
class WordAudio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Serializer\Groups(['word_audio:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(['word_audio:read'])]
    private ?string $word = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(['word_audio:read'])]
    private ?string $audioPath = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(['word_audio:read'])]
    private ?string $userUid = null;

    #[ORM\Column]
    #[Serializer\Groups(['word_audio:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWord(): ?string
    {
        return $this->word;
    }

    public function setWord(string $word): static
    {
        $this->word = $word;
        return $this;
    }

    public function getAudioPath(): ?string
    {
        return $this->audioPath;
    }

    public function setAudioPath(string $audioPath): static
    {
        $this->audioPath = $audioPath;
        return $this;
    }

    public function getUserUid(): ?string
    {
        return $this->userUid;
    }

    public function setUserUid(string $userUid): static
    {
        $this->userUid = $userUid;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}