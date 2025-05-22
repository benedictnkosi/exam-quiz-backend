<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'question')]
#[ORM\Index(name: 'question_subject', columns: ['subject'])]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $question = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(type: Types::TEXT, length: 16777215, nullable: true)]
    private ?string $answer = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $term = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanation = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $higherGrade = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $posted = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $answerImage = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'capturer', referencedColumnName: 'id')]
    private ?Learner $capturer = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false, options: ['default' => 'new'])]
    private string $status = 'new';

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'reviewer', referencedColumnName: 'id')]
    private ?Learner $reviewer = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTime $created = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTime $updated = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $reviewedAt = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $questionImagePath = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiExplanation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $answerSheet = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $otherContextImages = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(name: 'subject', referencedColumnName: 'id')]
    private ?Subject $subject = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false, options: ['default' => 'CAPS'])]
    private string $curriculum = 'CAPS';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $relatedQuestionIds = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $topic = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $ai = false;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $questionNumber = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $steps = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false, options: ['default' => 'new'])]
    private string $practice_status = 'new';

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

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(?string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

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

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): static
    {
        $this->answer = $answer;

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

    public function getTerm(): ?int
    {
        return $this->term;
    }

    public function setTerm(?int $term): static
    {
        $this->term = $term;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

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

    public function getHigherGrade(): ?int
    {
        return $this->higherGrade;
    }

    public function setHigherGrade(?int $higherGrade): static
    {
        $this->higherGrade = $higherGrade;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getAnswerImage(): ?string
    {
        return $this->answerImage;
    }

    public function setAnswerImage(?string $answerImage): static
    {
        $this->answerImage = $answerImage;

        return $this;
    }

    public function getCapturer(): ?Learner
    {
        return $this->capturer;
    }

    public function setCapturer(?Learner $capturer): static
    {
        $this->capturer = $capturer;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReviewer(): ?Learner
    {
        return $this->reviewer;
    }

    public function setReviewer(?Learner $reviewer): static
    {
        $this->reviewer = $reviewer;

        return $this;
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setCreated(?\DateTime $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?\DateTime
    {
        return $this->updated;
    }

    public function setUpdated(?\DateTime $updated): static
    {
        $this->updated = $updated;

        return $this;
    }

    public function getReviewedAt(): ?\DateTime
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTime $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getQuestionImagePath(): ?string
    {
        return $this->questionImagePath;
    }

    public function setQuestionImagePath(?string $questionImagePath): static
    {
        $this->questionImagePath = $questionImagePath;

        return $this;
    }

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function isPosted(): ?bool
    {
        return $this->posted;
    }

    public function setPosted(?bool $posted): static
    {
        $this->posted = $posted;

        return $this;
    }

    public function getAiExplanation(): ?string
    {
        return $this->aiExplanation;
    }

    public function setAiExplanation(?string $aiExplanation): static
    {
        $this->aiExplanation = $aiExplanation;
        return $this;
    }

    public function getCurriculum(): string
    {
        return $this->curriculum;
    }

    public function setCurriculum(string $curriculum): static
    {
        $this->curriculum = $curriculum;
        return $this;
    }

    public function getRelatedQuestionIds(): ?array
    {
        return $this->relatedQuestionIds;
    }

    public function setRelatedQuestionIds(?array $relatedQuestionIds): static
    {
        $this->relatedQuestionIds = $relatedQuestionIds;
        return $this;
    }

    public function getAnswerSheet(): ?string
    {
        return $this->answerSheet;
    }

    public function setAnswerSheet(?string $answerSheet): static
    {
        $this->answerSheet = $answerSheet;
        return $this;
    }

    public function getOtherContextImages(): ?array
    {
        return $this->otherContextImages;
    }

    public function setOtherContextImages(?array $otherContextImages): static
    {
        $this->otherContextImages = $otherContextImages;
        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    public function isAi(): bool
    {
        return $this->ai;
    }

    public function setAi(bool $ai): static
    {
        $this->ai = $ai;

        return $this;
    }

    public function getQuestionNumber(): ?string
    {
        return $this->questionNumber;
    }

    public function setQuestionNumber(?string $questionNumber): static
    {
        $this->questionNumber = $questionNumber;
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

    public function getPracticeStatus(): string
    {
        return $this->practice_status;
    }

    public function setPracticeStatus(string $practice_status): static
    {
        $this->practice_status = $practice_status;
        return $this;
    }
}
