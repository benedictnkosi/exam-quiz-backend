<?php

namespace App\Entity;

use App\Repository\TenderBidWinnerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: TenderBidWinnerRepository::class)]
#[ORM\Table(name: 'tender_bid_winners')]
#[ORM\Index(name: 'tender_bid_winner_tender_idx', columns: ['tender_id'])]
#[ORM\Index(name: 'tender_bid_winner_company_idx', columns: ['company_id'])]
#[Serializer\ExclusionPolicy('none')]
class TenderBidWinner
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tender::class)]
    #[ORM\JoinColumn(name: 'tender_id', referencedColumnName: 'id', nullable: false)]
    #[Serializer\Type('App\Entity\Tender')]
    private ?Tender $tender = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    #[Serializer\Type('App\Entity\Company')]
    private ?Company $company = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $companyName;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Type('DateTime')]
    private \DateTimeInterface $created;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Serializer\Type('DateTime')]
    private \DateTimeInterface $updated;

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

    public function getTender(): ?Tender
    {
        return $this->tender;
    }

    public function setTender(?Tender $tender): static
    {
        $this->tender = $tender;
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenderId' => $this->tender?->getId(),
            'companyId' => $this->company?->getId(),
            'companyName' => $this->companyName,
            'created' => $this->created->format('Y-m-d H:i:s'),
            'updated' => $this->updated->format('Y-m-d H:i:s'),
        ];
    }
} 