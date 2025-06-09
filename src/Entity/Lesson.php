<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'language_lesson')]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(name: 'lesson_order', type: 'integer')]
    private int $lessonOrder;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Unit $unit;

    public function getLessonOrder(): int
    {
        return $this->lessonOrder;
    }

    public function setLessonOrder(int $lessonOrder): self
    {
        $this->lessonOrder = $lessonOrder;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // Getters and setters ...
}