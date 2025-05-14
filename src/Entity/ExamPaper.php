<?php

namespace App\Entity;

use App\Repository\ExamPaperRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: ExamPaperRepository::class)]
class ExamPaper
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Learner::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?Learner $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $paperName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $memoName = null;

    #[ORM\Column]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?int $numberOfQuestions = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?array $images = null;

    #[ORM\Column(length: 255)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $currentQuestion = null;

    #[ORM\Column(length: 20)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $status = null;

    #[ORM\Column(length: 100)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $subjectName = null;

    #[ORM\Column]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?int $grade = null;

    #[ORM\Column]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?int $year = null;

    #[ORM\Column(length: 20)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $term = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $paperOpenAiFileId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?string $memoOpenAiFileId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?array $questionNumbers = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Serializer\Groups(["exam_paper:read"])]
    private ?array $questionProgress = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Learner
    {
        return $this->user;
    }

    public function setUser(?Learner $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPaperName(): ?string
    {
        return $this->paperName;
    }

    public function setPaperName(string $paperName): static
    {
        $this->paperName = $paperName;
        return $this;
    }

    public function getMemoName(): ?string
    {
        return $this->memoName;
    }

    public function setMemoName(string $memoName): static
    {
        $this->memoName = $memoName;
        return $this;
    }

    public function getPaperOpenAiFileId(): ?string
    {
        return $this->paperOpenAiFileId;
    }

    public function setPaperOpenAiFileId(?string $paperOpenAiFileId): static
    {
        $this->paperOpenAiFileId = $paperOpenAiFileId;
        return $this;
    }

    public function getMemoOpenAiFileId(): ?string
    {
        return $this->memoOpenAiFileId;
    }

    public function setMemoOpenAiFileId(?string $memoOpenAiFileId): static
    {
        $this->memoOpenAiFileId = $memoOpenAiFileId;
        return $this;
    }

    public function getSubjectName(): ?string
    {
        return $this->subjectName;
    }

    public function setSubjectName(string $subjectName): static
    {
        $this->subjectName = $subjectName;
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

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(string $term): static
    {
        $this->term = $term;
        return $this;
    }

    public function getNumberOfQuestions(): ?int
    {
        return $this->numberOfQuestions;
    }

    public function setNumberOfQuestions(int $numberOfQuestions): static
    {
        $this->numberOfQuestions = $numberOfQuestions;
        return $this;
    }

    public function getImages(): ?array
    {
        return $this->images;
    }

    public function setImages(?array $images): self
    {
        $this->images = $images;
        return $this;
    }

    public function addImage(string $questionNumber, string $imagePath): self
    {
        if ($this->images === null) {
            $this->images = [];
        }
        $this->images[$questionNumber] = $imagePath;
        return $this;
    }

    public function getImageForQuestion(string $questionNumber): ?string
    {
        return $this->images[$questionNumber] ?? null;
    }

    public function getQuestionNumbers(): ?array
    {
        return $this->questionNumbers;
    }

    public function setQuestionNumbers(?array $questionNumbers): static
    {
        $this->questionNumbers = $questionNumbers;
        return $this;
    }

    public function getCurrentQuestion(): ?string
    {
        return $this->currentQuestion;
    }

    public function setCurrentQuestion(string $currentQuestion): static
    {
        $this->currentQuestion = $currentQuestion;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getQuestionProgress(): ?array
    {
        return $this->questionProgress;
    }

    public function setQuestionProgress(?array $questionProgress): static
    {
        $this->questionProgress = $questionProgress;
        return $this;
    }

    public function updateQuestionProgress(string $questionNumber, string $status, ?string $reason = null): static
    {
        if ($this->questionProgress === null) {
            $this->questionProgress = [];
        }

        $this->questionProgress[$questionNumber] = [
            'status' => $status,
            'reason' => $reason,
            'updated_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        return $this;
    }
}