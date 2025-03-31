<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'badge')]
class Badge
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'rules', type: Types::STRING, length: 255, nullable: true)]
    private ?string $rules = null;

    #[ORM\Column(name: 'image', type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'badge', targetEntity: LearnerBadge::class)]
    private Collection $learnerBadges;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->learnerBadges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
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

    public function getRules(): ?string
    {
        return $this->rules;
    }

    public function setRules(?string $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, LearnerBadge>
     */
    public function getLearnerBadges(): Collection
    {
        return $this->learnerBadges;
    }

    public function addLearnerBadge(LearnerBadge $learnerBadge): self
    {
        if (!$this->learnerBadges->contains($learnerBadge)) {
            $this->learnerBadges->add($learnerBadge);
            $learnerBadge->setBadge($this);
        }

        return $this;
    }

    public function removeLearnerBadge(LearnerBadge $learnerBadge): self
    {
        if ($this->learnerBadges->removeElement($learnerBadge)) {
            if ($learnerBadge->getBadge() === $this) {
                $learnerBadge->setBadge(null);
            }
        }

        return $this;
    }
}