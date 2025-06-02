<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'version')]
class Version
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $version;

    #[ORM\Column(type: 'boolean')]
    private $isSupported;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $deprecatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getIsSupported(): ?bool
    {
        return $this->isSupported;
    }

    public function setIsSupported(bool $isSupported): self
    {
        $this->isSupported = $isSupported;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getDeprecatedAt(): ?\DateTimeInterface
    {
        return $this->deprecatedAt;
    }

    public function setDeprecatedAt(?\DateTimeInterface $deprecatedAt): self
    {
        $this->deprecatedAt = $deprecatedAt;
        return $this;
    }
}