<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'language_word')]
class Word
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WordGroup::class, inversedBy: 'words')]
    #[ORM\JoinColumn(nullable: false)]
    private WordGroup $wordGroup;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $audio = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $audioCapturers = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $translations = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWordGroup(): WordGroup
    {
        return $this->wordGroup;
    }

    public function setWordGroup(WordGroup $wordGroup): self
    {
        $this->wordGroup = $wordGroup;
        return $this;
    }

    public function getAudio(): ?array
    {
        return $this->audio;
    }

    public function setAudio(?array $audio): self
    {
        $this->audio = $audio;
        return $this;
    }

    public function getAudioCapturers(): ?array
    {
        return $this->audioCapturers;
    }

    public function setAudioCapturers(?array $audioCapturers): self
    {
        $this->audioCapturers = $audioCapturers;
        return $this;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): self
    {
        $this->translations = $translations;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    // Getters and setters ...
}