<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'grade')]
#[Serializer\ExclusionPolicy('none')]
class Grade
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('integer')]
    private ?int $number = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['default' => 1])]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('integer')]
    private ?int $active = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getActive(): ?int
    {
        return $this->active;
    }

    public function setActive(?int $active): static
    {
        $this->active = $active;
        return $this;
    }
}
