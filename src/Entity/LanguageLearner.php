<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'language_learners')]
class LanguageLearner
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $uid;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $created;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $lastSeen;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'integer')]
    private int $points;

    #[ORM\Column(type: 'integer')]
    private int $streak;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $streakLastUpdated;

    #[ORM\Column(type: 'string', length: 255)]
    private string $avatar;

    #[ORM\Column(type: 'string', length: 255)]
    private string $expoPushToken;

    #[ORM\Column(type: 'string', length: 100)]
    private string $followMeCode;

    #[ORM\Column(type: 'string', length: 50)]
    private string $version;

    #[ORM\Column(type: 'string', length: 50)]
    private string $os;

    #[ORM\Column(type: 'boolean')]
    private bool $reminders;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getLastSeen(): \DateTime
    {
        return $this->lastSeen;
    }

    public function setLastSeen(\DateTime $lastSeen): self
    {
        $this->lastSeen = $lastSeen;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getStreak(): int
    {
        return $this->streak;
    }

    public function setStreak(int $streak): self
    {
        $this->streak = $streak;
        return $this;
    }

    public function getStreakLastUpdated(): \DateTime
    {
        return $this->streakLastUpdated;
    }

    public function setStreakLastUpdated(\DateTime $streakLastUpdated): self
    {
        $this->streakLastUpdated = $streakLastUpdated;
        return $this;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getExpoPushToken(): string
    {
        return $this->expoPushToken;
    }

    public function setExpoPushToken(string $expoPushToken): self
    {
        $this->expoPushToken = $expoPushToken;
        return $this;
    }

    public function getFollowMeCode(): string
    {
        return $this->followMeCode;
    }

    public function setFollowMeCode(string $followMeCode): self
    {
        $this->followMeCode = $followMeCode;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getOs(): string
    {
        return $this->os;
    }

    public function setOs(string $os): self
    {
        $this->os = $os;
        return $this;
    }

    public function getReminders(): bool
    {
        return $this->reminders;
    }

    public function setReminders(bool $reminders): self
    {
        $this->reminders = $reminders;
        return $this;
    }
}