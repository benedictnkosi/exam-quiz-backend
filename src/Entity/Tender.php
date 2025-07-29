<?php

namespace App\Entity;

use App\Repository\TenderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: TenderRepository::class)]
#[ORM\Table(name: 'tenders')]
#[ORM\Index(name: 'tender_category_idx', columns: ['category'])]
#[ORM\Index(name: 'tender_province_idx', columns: ['province'])]
#[ORM\Index(name: 'tender_organ_of_state_idx', columns: ['organOfState'])]
#[ORM\Index(name: 'tender_closing_date_idx', columns: ['closingDate'])]
#[Serializer\ExclusionPolicy('none')]
class Tender
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $category;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private string $tenderDescription;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Type('DateTime')]
    private ?\DateTimeInterface $advertisedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Serializer\Type('DateTime')]
    private ?\DateTimeInterface $awardedAt = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false, unique: true)]
    #[Serializer\Type('string')]
    private string $tenderNumber;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $organOfState;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Serializer\Type('string')]
    private string $province;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Serializer\Type('DateTime')]
    private \DateTimeInterface $closingDate;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Serializer\Type('string')]
    private string $placeWhereGoodsWorksOrServicesAreRequired;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $contactPerson;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    #[Serializer\Type('string')]
    private string $telephoneNumber;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Serializer\Type('array')]
    private ?array $successfulBidders = [];

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
        $this->successfulBidders = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getTenderDescription(): string
    {
        return $this->tenderDescription;
    }

    public function setTenderDescription(string $tenderDescription): static
    {
        $this->tenderDescription = $tenderDescription;
        return $this;
    }

    public function getAdvertisedAt(): ?\DateTimeInterface
    {
        return $this->advertisedAt;
    }

    public function setAdvertisedAt(?\DateTimeInterface $advertisedAt): static
    {
        $this->advertisedAt = $advertisedAt;
        return $this;
    }

    public function getAwardedAt(): ?\DateTimeInterface
    {
        return $this->awardedAt;
    }

    public function setAwardedAt(?\DateTimeInterface $awardedAt): static
    {
        $this->awardedAt = $awardedAt;
        return $this;
    }

    public function getTenderNumber(): string
    {
        return $this->tenderNumber;
    }

    public function setTenderNumber(string $tenderNumber): static
    {
        $this->tenderNumber = $tenderNumber;
        return $this;
    }

    public function getOrganOfState(): string
    {
        return $this->organOfState;
    }

    public function setOrganOfState(string $organOfState): static
    {
        $this->organOfState = $organOfState;
        return $this;
    }

    public function getProvince(): string
    {
        return $this->province;
    }

    public function setProvince(string $province): static
    {
        $this->province = $province;
        return $this;
    }

    public function getClosingDate(): \DateTimeInterface
    {
        return $this->closingDate;
    }

    public function setClosingDate(\DateTimeInterface $closingDate): static
    {
        $this->closingDate = $closingDate;
        return $this;
    }

    public function getPlaceWhereGoodsWorksOrServicesAreRequired(): string
    {
        return $this->placeWhereGoodsWorksOrServicesAreRequired;
    }

    public function setPlaceWhereGoodsWorksOrServicesAreRequired(string $placeWhereGoodsWorksOrServicesAreRequired): static
    {
        $this->placeWhereGoodsWorksOrServicesAreRequired = $placeWhereGoodsWorksOrServicesAreRequired;
        return $this;
    }

    public function getContactPerson(): string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(string $contactPerson): static
    {
        $this->contactPerson = $contactPerson;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephoneNumber(): string
    {
        return $this->telephoneNumber;
    }

    public function setTelephoneNumber(string $telephoneNumber): static
    {
        $this->telephoneNumber = $telephoneNumber;
        return $this;
    }

    public function getSuccessfulBidders(): ?array
    {
        return $this->successfulBidders;
    }

    public function setSuccessfulBidders(?array $successfulBidders): static
    {
        $this->successfulBidders = $successfulBidders;
        return $this;
    }

    public function addSuccessfulBidder(string $bidder): static
    {
        if (!in_array($bidder, $this->successfulBidders ?? [])) {
            $this->successfulBidders[] = $bidder;
        }
        return $this;
    }

    public function removeSuccessfulBidder(string $bidder): static
    {
        if (($key = array_search($bidder, $this->successfulBidders ?? [])) !== false) {
            unset($this->successfulBidders[$key]);
            $this->successfulBidders = array_values($this->successfulBidders);
        }
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
            'category' => $this->category,
            'tenderDescription' => $this->tenderDescription,
            'advertisedAt' => $this->advertisedAt?->format('Y-m-d H:i:s'),
            'awardedAt' => $this->awardedAt?->format('Y-m-d H:i:s'),
            'tenderNumber' => $this->tenderNumber,
            'organOfState' => $this->organOfState,
            'province' => $this->province,
            'closingDate' => $this->closingDate->format('Y-m-d H:i:s'),
            'placeWhereGoodsWorksOrServicesAreRequired' => $this->placeWhereGoodsWorksOrServicesAreRequired,
            'contactPerson' => $this->contactPerson,
            'email' => $this->email,
            'telephoneNumber' => $this->telephoneNumber,
            'successfulBidders' => $this->successfulBidders,
            'created' => $this->created->format('Y-m-d H:i:s'),
            'updated' => $this->updated->format('Y-m-d H:i:s'),
        ];
    }

    public function toArrayWithBidders(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'tenderDescription' => $this->tenderDescription,
            'advertisedAt' => $this->advertisedAt?->format('Y-m-d H:i:s'),
            'awardedAt' => $this->awardedAt?->format('Y-m-d H:i:s'),
            'tenderNumber' => $this->tenderNumber,
            'organOfState' => $this->organOfState,
            'province' => $this->province,
            'closingDate' => $this->closingDate->format('Y-m-d H:i:s'),
            'placeWhereGoodsWorksOrServicesAreRequired' => $this->placeWhereGoodsWorksOrServicesAreRequired,
            'contactPerson' => $this->contactPerson,
            'email' => $this->email,
            'telephoneNumber' => $this->telephoneNumber,
            'successfulBidders' => $this->successfulBidders,
            'created' => $this->created->format('Y-m-d H:i:s'),
            'updated' => $this->updated->format('Y-m-d H:i:s'),
        ];
    }
} 