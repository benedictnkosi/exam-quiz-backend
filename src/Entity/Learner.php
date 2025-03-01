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

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $score = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $notificationHour = null;


    #[ORM\Column(type: Types::STRING, length: 10, options: ['default' => 'learner'])]
    private string $role = 'learner';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $created;

    #[ORM\Column(name: 'lastSeen', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $lastseen;

    #[ORM\ManyToOne(targetEntity: Grade::class)]
    #[ORM\JoinColumn(name: 'grade', referencedColumnName: 'id')]
    private ?Grade $grade = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $schoolName = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $schoolAddress = null;

    //school latitude and longitude
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $schoolLatitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $schoolLongitude = null;


    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $terms = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $curriculum = null;

    public function __construct()
    {
        $this->created = new \DateTime();
        $this->lastseen = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): static
    {
        $this->uid = $uid;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }


    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getLastseen(): ?\DateTimeInterface
    {
        return $this->lastseen;
    }

    public function setLastseen(\DateTimeInterface $lastseen): static
    {
        $this->lastseen = $lastseen;

        return $this;
    }

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getNotificationHour(): ?int
    {
        return $this->notificationHour;
    }

    public function setNotificationHour(?int $notificationHour): static
    {
        $this->notificationHour = $notificationHour;

        return $this;
    }

    public function getSchoolName(): ?string
    {
        return $this->schoolName;
    }

    public function setSchoolName(?string $schoolName): static
    {
        $this->schoolName = $schoolName;

        return $this;
    }

    public function getSchoolAddress(): ?string
    {
        return $this->schoolAddress;
    }

    public function setSchoolAddress(?string $schoolAddress): static
    {
        $this->schoolAddress = $schoolAddress;

        return $this;
    }

    public function getSchoolLatitude(): ?float
    {
        return $this->schoolLatitude;
    }

    public function setSchoolLatitude(?float $schoolLatitude): static
    {
        $this->schoolLatitude = $schoolLatitude;

        return $this;
    }

    public function getSchoolLongitude(): ?float
    {
        return $this->schoolLongitude;
    }

    public function setSchoolLongitude(?float $schoolLongitude): static
    {
        $this->schoolLongitude = $schoolLongitude;

        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): static
    {
        $this->terms = $terms;

        return $this;
    }

    public function getCurriculum(): ?string
    {
        return $this->curriculum;
    }

    public function setCurriculum(?string $curriculum): static
    {
        $this->curriculum = $curriculum;

        return $this;
    }
}
