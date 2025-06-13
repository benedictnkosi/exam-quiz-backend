<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\LanguageQuestionTypes;

#[ORM\Entity]
#[ORM\Table(name: 'language_questions')]
class LanguageQuestions
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $correctOption = null;

    #[ORM\Column(name: 'question_order', type: 'integer')]
    private int $questionOrder;

    #[ORM\ManyToOne(targetEntity: LanguageQuestionTypes::class)]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id')]
    private LanguageQuestionTypes $type;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $blankIndex = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sentenceWords = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $direction = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(name: 'lesson_id', referencedColumnName: 'id')]
    private ?Lesson $lesson = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $matchType = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'approved';

    // Getters and setters ...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getCorrectOption(): ?int
    {
        return $this->correctOption;
    }

    public function setCorrectOption(?int $correctOption): self
    {
        $this->correctOption = $correctOption;
        return $this;
    }

    public function getQuestionOrder(): int
    {
        return $this->questionOrder;
    }

    public function setQuestionOrder(int $questionOrder): self
    {
        $this->questionOrder = $questionOrder;
        return $this;
    }

    public function getType(): LanguageQuestionTypes
    {
        return $this->type;
    }

    public function setType(LanguageQuestionTypes $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getBlankIndex(): ?int
    {
        return $this->blankIndex;
    }

    public function setBlankIndex(?int $blankIndex): self
    {
        $this->blankIndex = $blankIndex;
        return $this;
    }

    public function getSentenceWords(): ?array
    {
        return $this->sentenceWords;
    }

    public function setSentenceWords(?array $sentenceWords): self
    {
        $this->sentenceWords = $sentenceWords;
        return $this;
    }

    public function getDirection(): ?string
    {
        return $this->direction;
    }

    public function setDirection(?string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function setLesson(?Lesson $lesson): self
    {
        $this->lesson = $lesson;
        return $this;
    }

    public function getMatchType(): ?string
    {
        return $this->matchType;
    }

    public function setMatchType(?string $matchType): self
    {
        $this->matchType = $matchType;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
}