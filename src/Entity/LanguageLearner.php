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

    // Getters and setters ...
}