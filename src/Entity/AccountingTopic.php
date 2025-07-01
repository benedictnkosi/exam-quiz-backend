<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use JMS\Serializer\Annotation as Serializer;
use App\Repository\AccountingTopicRepository;

#[ORM\Entity(repositoryClass: AccountingTopicRepository::class)]
#[ORM\Table(name: 'accounting_topic')]
#[Serializer\ExclusionPolicy('none')]
class AccountingTopic
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $mainTopic;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $subTopic;

    #[ORM\OneToMany(mappedBy: 'accountingTopic', targetEntity: AccountingQuestion::class)]
    private $questions;

    public function __construct()
    {
        $this->questions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMainTopic(): string
    {
        return $this->mainTopic;
    }

    public function setMainTopic(string $mainTopic): static
    {
        $this->mainTopic = $mainTopic;
        return $this;
    }

    public function getSubTopic(): string
    {
        return $this->subTopic;
    }

    public function setSubTopic(string $subTopic): static
    {
        $this->subTopic = $subTopic;
        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection|AccountingQuestion[]
     */
    public function getQuestions()
    {
        return $this->questions;
    }

    public function addQuestion(AccountingQuestion $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions[] = $question;
            $question->setAccountingTopic($this);
        }
        return $this;
    }

    public function removeQuestion(AccountingQuestion $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getAccountingTopic() === $this) {
                $question->setAccountingTopic(null);
            }
        }
        return $this;
    }
} 