<?php

namespace App\Entity;

use App\Dto\MathStepDto;
use App\Repository\MathLessonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MathLessonRepository::class)]
class MathLesson
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    #[Groups(['math_lesson:read'])]
    private ?string $id = null;

    #[ORM\Column]
    #[Groups(['math_lesson:read'])]
    private ?int $grade = null;

    #[ORM\Column(length: 255)]
    #[Groups(['math_lesson:read'])]
    private ?string $topic = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['math_lesson:read'])]
    private ?string $subTopic = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: true)]
    private ?Question $question = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['math_lesson:read'])]
    private array $steps = [];

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getGrade(): ?int
    {
        return $this->grade;
    }

    public function setGrade(int $grade): static
    {
        $this->grade = $grade;
        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): static
    {
        $this->topic = $topic;
        return $this;
    }

    public function getSubTopic(): ?string
    {
        return $this->subTopic;
    }

    public function setSubTopic(string $subTopic): static
    {
        $this->subTopic = $subTopic;
        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): self
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return MathStepDto[]
     */
    public function getSteps(): array
    {
        return array_map(
            fn(array $step) => new MathStepDto(
                $step['step_number'],
                $step['prompt'],
                $step['expression'],
                $step['options'],
                $step['answer'],
                $step['hint'],
                $step['teach'],
                $step['final_expression'],
                $step['type'] ?? null
            ),
            $this->steps
        );
    }

    /**
     * @param MathStepDto[] $steps
     */
    public function setSteps(array $steps): static
    {
        $this->steps = array_map(
            fn(MathStepDto $step) => [
                'step_number' => $step->getStepNumber(),
                'prompt' => $step->getPrompt(),
                'expression' => $step->getExpression(),
                'options' => $step->getOptions(),
                'answer' => $step->getAnswer(),
                'hint' => $step->getHint(),
                'teach' => $step->getTeach(),
                'final_expression' => $step->getFinalExpression(),
                'type' => $step->getType()
            ],
            $steps
        );
        return $this;
    }

    public function addStep(MathStepDto $step): static
    {
        $this->steps[] = [
            'step_number' => $step->getStepNumber(),
            'prompt' => $step->getPrompt(),
            'expression' => $step->getExpression(),
            'options' => $step->getOptions(),
            'answer' => $step->getAnswer(),
            'hint' => $step->getHint(),
            'teach' => $step->getTeach(),
            'final_expression' => $step->getFinalExpression(),
            'type' => $step->getType()
        ];
        return $this;
    }
}