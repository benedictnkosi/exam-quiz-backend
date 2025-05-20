<?php

namespace App\Dto;

class MathStepDto implements \JsonSerializable
{
    private int $stepNumber;
    private string $prompt;
    private string $expression;
    private array $options;
    private string $answer;
    private string $hint;
    private string $teach;
    private string $finalExpression;
    private ?string $type = null;

    public function __construct(
        int $stepNumber,
        string $prompt,
        string $expression,
        array $options,
        string $answer,
        string $hint,
        string $teach,
        string $finalExpression,
        ?string $type = null
    ) {
        $this->stepNumber = $stepNumber;
        $this->prompt = $prompt;
        $this->expression = $expression;
        $this->options = $options;
        $this->answer = $answer;
        $this->hint = $hint;
        $this->teach = $teach;
        $this->finalExpression = $finalExpression;
        $this->type = $type;
    }

    public function getStepNumber(): int
    {
        return $this->stepNumber;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    public function getTeach(): string
    {
        return $this->teach;
    }

    public function getFinalExpression(): string
    {
        return $this->finalExpression;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function jsonSerialize(): array
    {
        return [
            'step_number' => $this->stepNumber,
            'type' => $this->type,
            'prompt' => $this->prompt,
            'expression' => $this->expression,
            'options' => $this->options,
            'answer' => $this->answer,
            'hint' => $this->hint,
            'teach' => $this->teach,
            'final_expression' => $this->finalExpression
        ];
    }
}