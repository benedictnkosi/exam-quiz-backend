<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'learner_completed_chapter')]
#[ORM\Index(name: 'learner_completed_chapter_learner_uid_idx', columns: ['learner_uid'])]
    #[ORM\Index(name: 'learner_completed_chapter_chapter_name_idx', columns: ['chapter_name'])]
#[ORM\Index(name: 'learner_completed_chapter_book_title_idx', columns: ['book_title'])]
class LearnerCompletedChapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'learner_uid', type: Types::STRING, length: 45, nullable: false)]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private string $learnerUid;

    #[ORM\Column(name: 'chapter_name', type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private string $chapterName;

    #[ORM\Column(name: 'book_title', type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private string $bookTitle;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private \DateTime $completedAt;

    #[ORM\Column(name: 'duration', type: Types::INTEGER, nullable: true)]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private ?int $duration = null;

    #[ORM\Column(name: 'score', type: Types::INTEGER, nullable: true)]
    #[Serializer\Groups(['learner_completed_chapter:read'])]
    private ?int $score = null;

    public function __construct()
    {
        $this->completedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLearnerUid(): string
    {
        return $this->learnerUid;
    }

    public function setLearnerUid(string $learnerUid): self
    {
        $this->learnerUid = $learnerUid;
        return $this;
    }

    public function getChapterName(): string
    {
        return $this->chapterName;
    }

    public function setChapterName(string $chapterName): self
    {
        $this->chapterName = $chapterName;
        return $this;
    }

    public function getBookTitle(): string
    {
        return $this->bookTitle;
    }

    public function setBookTitle(string $bookTitle): self
    {
        $this->bookTitle = $bookTitle;
        return $this;
    }

    public function getCompletedAt(): \DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTime $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }
} 