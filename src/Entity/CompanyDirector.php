<?php

namespace App\Entity;

use App\Repository\CompanyDirectorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: CompanyDirectorRepository::class)]
#[ORM\Table(name: 'company_directors')]
#[ORM\Index(name: 'company_director_company_idx', columns: ['company_id'])]
#[ORM\Index(name: 'company_director_id_number_idx', columns: ['director_id_number'])]
#[Serializer\ExclusionPolicy('none')]
class CompanyDirector
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Serializer\Type('integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Type('string')]
    private string $directorName;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    #[Serializer\Type('string')]
    private string $directorIdNumber;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    #[Serializer\Type('App\Entity\Company')]
    private ?Company $company = null;

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

    public function getDirectorName(): string
    {
        return $this->directorName;
    }

    public function setDirectorName(string $directorName): static
    {
        $this->directorName = $directorName;
        return $this;
    }

    public function getDirectorIdNumber(): string
    {
        return $this->directorIdNumber;
    }

    public function setDirectorIdNumber(string $directorIdNumber): static
    {
        $this->directorIdNumber = $directorIdNumber;
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
            'directorName' => $this->directorName,
            'directorIdNumber' => $this->directorIdNumber,
            'companyId' => $this->company?->getId(),
            'created' => $this->created->format('Y-m-d H:i:s'),
            'updated' => $this->updated->format('Y-m-d H:i:s'),
        ];
    }
} 