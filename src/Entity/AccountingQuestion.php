<?php

namespace App\Entity;

use App\Repository\AccountingQuestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: AccountingQuestionRepository::class)]
#[ORM\Table(name: 'accounting_question')]
#[ORM\Index(name: 'accounting_question_topic_idx', columns: ['topic'])]
#[ORM\Index(name: 'accounting_question_level_idx', columns: ['level'])]
#[ORM\Index(name: 'accounting_question_type_idx', columns: ['questionType'])]
#[Serializer\ExclusionPolicy('none')]
class AccountingQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Serializer\Type('string')]
    private string $level;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    #[Serializer\Type('string')]
    private string $questionId;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    #[Serializer\Type('string')]
    private string $questionType;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private string $prompt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $options = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $answer = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $categories = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $items = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $correctOrder = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $pairs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $context = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $steps = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serializer\Type('string')]
    private ?string $explanation = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
    #[Serializer\Type('boolean')]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Type('DateTime')]
    private \DateTimeInterface $created;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Type('DateTime')]
    private \DateTimeInterface $updated;

    #[ORM\ManyToOne(targetEntity: AccountingTopic::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: 'accounting_topic_id', referencedColumnName: 'id', nullable: false)]
    private ?AccountingTopic $accountingTopic = null;

    public function __construct()
    {
        $now = new \DateTime();
        $this->created = $now;
        $this->updated = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getQuestionId(): string
    {
        return $this->questionId;
    }

    public function setQuestionId(string $questionId): static
    {
        $this->questionId = $questionId;
        return $this;
    }

    public function getQuestionType(): string
    {
        return $this->questionType;
    }

    public function setQuestionType(string $questionType): static
    {
        $this->questionType = $questionType;
        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): static
    {
        $this->answer = $answer;
        return $this;
    }

    public function getCategories(): ?array
    {
        return $this->categories;
    }

    public function setCategories(?array $categories): static
    {
        $this->categories = $categories;
        return $this;
    }

    public function getItems(): ?array
    {
        return $this->items;
    }

    public function setItems(?array $items): static
    {
        $this->items = $items;
        return $this;
    }

    public function getCorrectOrder(): ?array
    {
        return $this->correctOrder;
    }

    public function setCorrectOrder(?array $correctOrder): static
    {
        $this->correctOrder = $correctOrder;
        return $this;
    }

    public function getPairs(): ?array
    {
        return $this->pairs;
    }

    public function setPairs(?array $pairs): static
    {
        $this->pairs = $pairs;
        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getSteps(): ?array
    {
        return $this->steps;
    }

    public function setSteps(?array $steps): static
    {
        $this->steps = $steps;
        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function getCreated(): \DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): static
    {
        $this->created = $created;
        return $this;
    }

    public function getUpdated(): \DateTimeInterface
    {
        return $this->updated;
    }

    public function setUpdated(\DateTimeInterface $updated): static
    {
        $this->updated = $updated;
        return $this;
    }

    public function getAccountingTopic(): ?AccountingTopic
    {
        return $this->accountingTopic;
    }

    public function setAccountingTopic(?AccountingTopic $accountingTopic): static
    {
        $this->accountingTopic = $accountingTopic;
        return $this;
    }

    /**
     * Convert the entity to the original JSON format for API responses
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->questionId,
            'type' => $this->questionType,
            'prompt' => $this->prompt,
            'accounting_topic' => $this->accountingTopic ? [
                'id' => $this->accountingTopic->getId(),
                'mainTopic' => $this->accountingTopic->getMainTopic(),
                'subTopic' => $this->accountingTopic->getSubTopic(),
            ] : null,
        ];

        // Add type-specific fields
        switch ($this->questionType) {
            case 'tap-to-select':
                $data['options'] = $this->options;
                $data['answer'] = $this->answer;
                break;
            case 'categorise':
                $data['categories'] = $this->categories;
                $data['items'] = $this->items;
                break;
            case 'true-false':
                $data['answer'] = $this->answer;
                if ($this->explanation) {
                    $data['explanation'] = $this->explanation;
                }
                break;
            case 'drag-to-sort':
                $data['items'] = $this->options; // items are stored in options field
                $data['correct_order'] = $this->correctOrder;
                break;
            case 'matching':
                $data['pairs'] = $this->pairs;
                break;
            case 'step-flow':
                // Handle both old format (options/answer) and new format (steps array)
                if ($this->steps) {
                    // New format with steps array
                    $data['steps'] = $this->steps;
                } else {
                    // Old format with options and answer
                    $data['options'] = $this->options;
                    $data['answer'] = $this->answer;
                    if ($this->explanation) {
                        $data['explanation'] = $this->explanation;
                    }
                }
                break;
            case 'multi-step':
                if ($this->context) {
                    $data['context'] = $this->context;
                }
                if ($this->steps) {
                    $data['steps'] = $this->steps;
                }
                break;
        }

        return $data;
    }
} 