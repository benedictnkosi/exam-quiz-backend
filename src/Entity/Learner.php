<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'learner')]
#[ORM\Index(name: 'learner_grade_idx', columns: ['grade'])]
class Learner
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $uid = null;

    #[ORM\ManyToOne(targetEntity: Grade::class)]
    #[ORM\JoinColumn(name: 'grade', referencedColumnName: 'id')]
    private ?Grade $grade = null;

    #[ORM\Column(name: 'points', type: Types::INTEGER, options: ['default' => 0])]
    private int $points = 0;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'notification_hour', type: Types::SMALLINT, options: ['default' => 0])]
    private int $notificationHour = 0;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['default' => 'learner'])]
    private string $role = 'learner';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $created;

    #[ORM\Column(name: 'lastSeen', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $lastSeen;

    #[ORM\Column(name: 'school_address', type: Types::STRING, length: 500, nullable: true)]
    private ?string $schoolAddress = null;

    #[ORM\Column(name: 'school_name', type: Types::STRING, length: 100, nullable: true)]
    private ?string $schoolName = null;

    #[ORM\Column(name: 'school_latitude', type: Types::FLOAT, nullable: true)]
    private ?float $schoolLatitude = null;

    #[ORM\Column(name: 'school_longitude', type: Types::FLOAT, nullable: true)]
    private ?float $schoolLongitude = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $terms = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $curriculum = null;

    #[ORM\Column(name: 'private_school', type: Types::BOOLEAN, nullable: true)]
    private ?bool $privateSchool = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private float $rating = 0;

    #[ORM\Column(name: 'rating_cancelled', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $ratingCancelled = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $streak = 0;

    #[ORM\Column(name: 'streak_last_updated', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $streakLastUpdated;

    #[ORM\Column(type: Types::STRING, options: ['default' => '8.png'])]
    private string $avatar = '8.png';

    public function __construct()
    {
        $this->created = new \DateTime();
        $this->lastSeen = new \DateTime();
        $this->streakLastUpdated = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): self
    {
        $this->grade = $grade;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getNotificationHour(): int
    {
        return $this->notificationHour;
    }

    public function setNotificationHour(int $notificationHour): self
    {
        $this->notificationHour = $notificationHour;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
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

    public function getSchoolAddress(): ?string
    {
        return $this->schoolAddress;
    }

    public function setSchoolAddress(?string $schoolAddress): self
    {
        $this->schoolAddress = $schoolAddress;
        return $this;
    }

    public function getSchoolName(): ?string
    {
        return $this->schoolName;
    }

    public function setSchoolName(?string $schoolName): self
    {
        $this->schoolName = $schoolName;
        return $this;
    }

    public function getSchoolLatitude(): ?float
    {
        return $this->schoolLatitude;
    }

    public function setSchoolLatitude(?float $schoolLatitude): self
    {
        $this->schoolLatitude = $schoolLatitude;
        return $this;
    }

    public function getSchoolLongitude(): ?float
    {
        return $this->schoolLongitude;
    }

    public function setSchoolLongitude(?float $schoolLongitude): self
    {
        $this->schoolLongitude = $schoolLongitude;
        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): self
    {
        $this->terms = $terms;
        return $this;
    }

    public function getCurriculum(): ?string
    {
        return $this->curriculum;
    }

    public function setCurriculum(?string $curriculum): self
    {
        $this->curriculum = $curriculum;
        return $this;
    }

    public function getPrivateSchool(): ?bool
    {
        return $this->privateSchool;
    }

    public function setPrivateSchool(?bool $privateSchool): self
    {
        $this->privateSchool = $privateSchool;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function setRating(float $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getRatingCancelled(): ?\DateTime
    {
        return $this->ratingCancelled;
    }

    public function setRatingCancelled(?\DateTime $ratingCancelled): self
    {
        $this->ratingCancelled = $ratingCancelled;
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
}
