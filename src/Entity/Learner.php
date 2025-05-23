<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'learner')]
#[ORM\Index(name: 'learner_grade_idx', columns: ['grade'])]
class Learner
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Groups(['learner:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $uid = null;

    #[ORM\ManyToOne(targetEntity: Grade::class)]
    #[ORM\JoinColumn(name: 'grade', referencedColumnName: 'id')]
    #[Serializer\Groups(['learner:read'])]
    private ?Grade $grade = null;

    #[ORM\Column(name: 'points', type: Types::INTEGER, options: ['default' => 0])]
    #[Serializer\Groups(['learner:read'])]
    private int $points = 0;

    #[ORM\Column(name: 'reading_points', type: Types::INTEGER, options: ['default' => 0])]
    #[Serializer\Groups(['learner:read'])]
    private int $readingPoints = 0;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $name = null;

    #[ORM\Column(name: 'notification_hour', type: Types::SMALLINT, options: ['default' => 0])]
    #[Serializer\Groups(['learner:read'])]
    private int $notificationHour = 0;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['default' => 'learner'])]
    #[Serializer\Groups(['learner:read'])]
    private string $role = 'learner';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Groups(['learner:read'])]
    private \DateTime $created;

    #[ORM\Column(name: 'lastSeen', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Groups(['learner:read'])]
    private \DateTime $lastSeen;

    #[ORM\Column(name: 'school_address', type: Types::STRING, length: 500, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $schoolAddress = null;

    #[ORM\Column(name: 'school_name', type: Types::STRING, length: 100, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $schoolName = null;

    #[ORM\Column(name: 'school_latitude', type: Types::FLOAT, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?float $schoolLatitude = null;

    #[ORM\Column(name: 'school_longitude', type: Types::FLOAT, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?float $schoolLongitude = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $terms = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $curriculum = null;

    #[ORM\Column(name: 'private_school', type: Types::BOOLEAN, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?bool $privateSchool = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    #[Serializer\Groups(['learner:read'])]
    private float $rating = 0;

    #[ORM\Column(name: 'rating_cancelled', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?\DateTime $ratingCancelled = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Serializer\Groups(['learner:read'])]
    private int $streak = 0;

    #[ORM\Column(name: 'streak_last_updated', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Groups(['learner:read'])]
    private \DateTime $streakLastUpdated;

    #[ORM\Column(type: Types::STRING, options: ['default' => '8.png'])]
    #[Serializer\Groups(['learner:read'])]
    private string $avatar = '8.png';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $expoPushToken = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $followMeCode = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $version = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $os = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('array')]
    private ?array $timetable = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('array')]
    private ?array $events = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('array')]
    private ?array $topicLessonsTracker = null;

    #[ORM\Column(name: 'new_thread_notification', type: Types::BOOLEAN, options: ['default' => true])]
    #[Serializer\Groups(['learner:read'])]
    private bool $newThreadNotification = true;

    #[ORM\Column(name: 'public_profile', type: Types::BOOLEAN, options: ['default' => true])]
    #[Serializer\Groups(['learner:read'])]
    private bool $publicProfile = true;

    #[ORM\OneToMany(mappedBy: 'learner', targetEntity: LearnerBadge::class)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\MaxDepth(1)]
    private Collection $learnerBadges;

    #[ORM\OneToMany(mappedBy: 'learner', targetEntity: LearnerNote::class)]
    #[Serializer\Groups([])]
    #[Serializer\MaxDepth(1)]
    private Collection $notes;

    #[ORM\OneToMany(mappedBy: 'learner', targetEntity: Todo::class)]
    #[Serializer\Groups([])]
    #[Serializer\MaxDepth(1)]
    private Collection $todos;

    #[ORM\OneToMany(mappedBy: 'follower', targetEntity: LearnerFollowing::class)]
    #[Serializer\Groups([])]
    #[Serializer\MaxDepth(1)]
    private Collection $following;

    #[ORM\OneToMany(mappedBy: 'following', targetEntity: LearnerFollowing::class)]
    #[Serializer\Groups([])]
    #[Serializer\MaxDepth(1)]
    private Collection $followers;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    #[Serializer\Type('array')]
    private ?array $careerAdvice = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Serializer\Groups(['learner:read'])]
    private int $readingLevel = 1;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Serializer\Groups(['learner:read'])]
    private ?string $subscription = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'active'])]
    #[Serializer\Groups(['learner:read'])]
    private string $status = 'active';

    #[ORM\OneToMany(mappedBy: 'learner', targetEntity: LearnerPodcastRequest::class)]
    private Collection $podcastRequests;

    public function __construct()
    {
        $this->created = new \DateTime();
        $this->lastSeen = new \DateTime();
        $this->streakLastUpdated = new \DateTime();
        $this->learnerBadges = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->todos = new ArrayCollection();
        $this->following = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->podcastRequests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getGrade(): ?Grade
    {
        return $this->grade;
    }

    public function setGrade(?Grade $grade): self
    {
        $this->grade = $grade;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getReadingPoints(): int
    {
        return $this->readingPoints;
    }

    public function setReadingPoints(int $readingPoints): self
    {
        $this->readingPoints = $readingPoints;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getNotificationHour(): int
    {
        return $this->notificationHour;
    }

    public function setNotificationHour(int $notificationHour): self
    {
        $this->notificationHour = $notificationHour;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getLastSeen(): \DateTime
    {
        return $this->lastSeen;
    }

    public function setLastSeen(\DateTime $lastSeen): self
    {
        $this->lastSeen = $lastSeen;
        return $this;
    }

    public function getSchoolAddress(): ?string
    {
        return $this->schoolAddress;
    }

    public function setSchoolAddress(?string $schoolAddress): self
    {
        $this->schoolAddress = $schoolAddress;
        return $this;
    }

    public function getSchoolName(): ?string
    {
        return $this->schoolName;
    }

    public function setSchoolName(?string $schoolName): self
    {
        $this->schoolName = $schoolName;
        return $this;
    }

    public function getSchoolLatitude(): ?float
    {
        return $this->schoolLatitude;
    }

    public function setSchoolLatitude(?float $schoolLatitude): self
    {
        $this->schoolLatitude = $schoolLatitude;
        return $this;
    }

    public function getSchoolLongitude(): ?float
    {
        return $this->schoolLongitude;
    }

    public function setSchoolLongitude(?float $schoolLongitude): self
    {
        $this->schoolLongitude = $schoolLongitude;
        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): self
    {
        $this->terms = $terms;
        return $this;
    }

    public function getCurriculum(): ?string
    {
        return $this->curriculum;
    }

    public function setCurriculum(?string $curriculum): self
    {
        $this->curriculum = $curriculum;
        return $this;
    }

    public function getPrivateSchool(): ?bool
    {
        return $this->privateSchool;
    }

    public function setPrivateSchool(?bool $privateSchool): self
    {
        $this->privateSchool = $privateSchool;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function setRating(float $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getRatingCancelled(): ?\DateTime
    {
        return $this->ratingCancelled;
    }

    public function setRatingCancelled(?\DateTime $ratingCancelled): self
    {
        $this->ratingCancelled = $ratingCancelled;
        return $this;
    }

    public function getStreak(): int
    {
        return $this->streak;
    }

    public function setStreak(int $streak): self
    {
        $this->streak = $streak;
        return $this;
    }

    public function getStreakLastUpdated(): \DateTime
    {
        return $this->streakLastUpdated;
    }

    public function setStreakLastUpdated(\DateTime $streakLastUpdated): self
    {
        $this->streakLastUpdated = $streakLastUpdated;
        return $this;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getExpoPushToken(): ?string
    {
        return $this->expoPushToken;
    }

    public function setExpoPushToken(?string $expoPushToken): self
    {
        $this->expoPushToken = $expoPushToken;
        return $this;
    }

    public function getFollowMeCode(): ?string
    {
        return $this->followMeCode;
    }

    public function setFollowMeCode(?string $followMeCode): self
    {
        $this->followMeCode = $followMeCode;
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function setOs(?string $os): self
    {
        $this->os = $os;
        return $this;
    }

    public function getTimetable(): ?array
    {
        return $this->timetable;
    }

    public function setTimetable(?array $timetable): self
    {
        $this->timetable = $timetable;
        return $this;
    }

    public function getLearnerBadges(): Collection
    {
        return $this->learnerBadges;
    }

    /**
     * @return Collection<int, LearnerNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(LearnerNote $note): static
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setLearner($this);
        }

        return $this;
    }

    public function removeNote(LearnerNote $note): static
    {
        if ($this->notes->removeElement($note)) {
            // set the owning side to null (unless already changed)
            if ($note->getLearner() === $this) {
                $note->setLearner(null);
            }
        }

        return $this;
    }

    public function getTodos(): Collection
    {
        return $this->todos;
    }

    public function addTodo(Todo $todo): self
    {
        if (!$this->todos->contains($todo)) {
            $this->todos->add($todo);
            $todo->setLearner($this);
        }
        return $this;
    }

    public function removeTodo(Todo $todo): self
    {
        if ($this->todos->removeElement($todo)) {
            if ($todo->getLearner() === $this) {
                $todo->setLearner(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, LearnerFollowing>
     */
    public function getFollowing(): Collection
    {
        return $this->following;
    }

    public function addFollowing(LearnerFollowing $following): self
    {
        if (!$this->following->contains($following)) {
            $this->following->add($following);
            $following->setFollower($this);
        }

        return $this;
    }

    public function removeFollowing(LearnerFollowing $following): self
    {
        if ($this->following->removeElement($following)) {
            // set the owning side to null (unless already changed)
            if ($following->getFollower() === $this) {
                $following->setFollower(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LearnerFollowing>
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(LearnerFollowing $follower): self
    {
        if (!$this->followers->contains($follower)) {
            $this->followers->add($follower);
            $follower->setFollowing($this);
        }

        return $this;
    }

    public function removeFollower(LearnerFollowing $follower): self
    {
        if ($this->followers->removeElement($follower)) {
            // set the owning side to null (unless already changed)
            if ($follower->getFollowing() === $this) {
                $follower->setFollowing(null);
            }
        }

        return $this;
    }

    public function getNewThreadNotification(): bool
    {
        return $this->newThreadNotification;
    }

    public function setNewThreadNotification(bool $newThreadNotification): self
    {
        $this->newThreadNotification = $newThreadNotification;
        return $this;
    }

    public function getEvents(): ?array
    {
        return $this->events;
    }

    public function setEvents(?array $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function getTopicLessonsTracker(): ?array
    {
        return $this->topicLessonsTracker;
    }

    public function setTopicLessonsTracker(?array $topicLessonsTracker): self
    {
        $this->topicLessonsTracker = $topicLessonsTracker;
        return $this;
    }

    public function getPublicProfile(): bool
    {
        return $this->publicProfile;
    }

    public function setPublicProfile(bool $publicProfile): self
    {
        $this->publicProfile = $publicProfile;
        return $this;
    }

    public function getCareerAdvice(): ?array
    {
        return $this->careerAdvice;
    }

    public function setCareerAdvice(?array $careerAdvice): self
    {
        $this->careerAdvice = $careerAdvice;
        return $this;
    }

    public function getReadingLevel(): int
    {
        return $this->readingLevel;
    }

    public function setReadingLevel(int $readingLevel): self
    {
        $this->readingLevel = $readingLevel;
        return $this;
    }

    public function getSubscription(): ?string
    {
        return $this->subscription;
    }

    public function setSubscription(?string $subscription): self
    {
        $this->subscription = $subscription;
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

    /**
     * @return Collection<int, LearnerPodcastRequest>
     */
    public function getPodcastRequests(): Collection
    {
        return $this->podcastRequests;
    }

    public function addPodcastRequest(LearnerPodcastRequest $podcastRequest): static
    {
        if (!$this->podcastRequests->contains($podcastRequest)) {
            $this->podcastRequests->add($podcastRequest);
            $podcastRequest->setLearner($this);
        }
        return $this;
    }

    public function removePodcastRequest(LearnerPodcastRequest $podcastRequest): static
    {
        if ($this->podcastRequests->removeElement($podcastRequest)) {
            if ($podcastRequest->getLearner() === $this) {
                $podcastRequest->setLearner(null);
            }
        }
        return $this;
    }
}
