<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: "tender_companies")]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $enterpriseNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $enterpriseName = null;

    #[ORM\Column(length: 100)]
    private ?string $enterpriseType = null;

    #[ORM\Column(length: 100)]
    private ?string $enterpriseStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $complianceNotice = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $registrationDate = null;

    #[ORM\Column(type: 'text')]
    private ?string $physicalAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $postalAddress = null;

    #[ORM\Column(length: 32, options: ['default' => 'active'])]
    private ?string $status = 'active';

    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Business::class, cascade: ['persist', 'remove'])]
    private Collection $businesses;

    public function __construct()
    {
        $this->businesses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnterpriseNumber(): ?string
    {
        return $this->enterpriseNumber;
    }

    public function setEnterpriseNumber(string $enterpriseNumber): self
    {
        $this->enterpriseNumber = $enterpriseNumber;
        return $this;
    }

    public function getEnterpriseName(): ?string
    {
        return $this->enterpriseName;
    }

    public function setEnterpriseName(string $enterpriseName): self
    {
        $this->enterpriseName = $enterpriseName;
        return $this;
    }

    public function getEnterpriseType(): ?string
    {
        return $this->enterpriseType;
    }

    public function setEnterpriseType(string $enterpriseType): self
    {
        $this->enterpriseType = $enterpriseType;
        return $this;
    }

    public function getEnterpriseStatus(): ?string
    {
        return $this->enterpriseStatus;
    }

    public function setEnterpriseStatus(string $enterpriseStatus): self
    {
        $this->enterpriseStatus = $enterpriseStatus;
        return $this;
    }

    public function getComplianceNotice(): ?string
    {
        return $this->complianceNotice;
    }

    public function setComplianceNotice(?string $complianceNotice): self
    {
        $this->complianceNotice = $complianceNotice;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(?\DateTimeInterface $registrationDate): self
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    public function getPhysicalAddress(): ?string
    {
        return $this->physicalAddress;
    }

    public function setPhysicalAddress(string $physicalAddress): self
    {
        $this->physicalAddress = $physicalAddress;
        return $this;
    }

    public function getPostalAddress(): ?string
    {
        return $this->postalAddress;
    }

    public function setPostalAddress(?string $postalAddress): self
    {
        $this->postalAddress = $postalAddress;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, Business>
     */
    public function getBusinesses(): Collection
    {
        return $this->businesses;
    }

    public function addBusiness(Business $business): self
    {
        if (!$this->businesses->contains($business)) {
            $this->businesses->add($business);
            $business->setCompany($this);
        }

        return $this;
    }

    public function removeBusiness(Business $business): self
    {
        if ($this->businesses->removeElement($business)) {
            // set the owning side to null (unless already changed)
            if ($business->getCompany() === $this) {
                $business->setCompany(null);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'enterpriseNumber' => $this->enterpriseNumber,
            'enterpriseName' => $this->enterpriseName,
            'enterpriseType' => $this->enterpriseType,
            'enterpriseStatus' => $this->enterpriseStatus,
            'complianceNotice' => $this->complianceNotice,
            'registrationDate' => $this->registrationDate?->format('Y-m-d'),
            'physicalAddress' => $this->physicalAddress,
            'postalAddress' => $this->postalAddress,
            'status' => $this->status,
        ];
    }
} 