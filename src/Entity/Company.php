<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: "companies")]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private array $companyNames = [];

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    private ?string $surname = null;

    #[ORM\Column(length: 50)]
    private ?string $idNumber = null;

    #[ORM\Column(type: 'text')]
    private ?string $residentialAddress = null;

    #[ORM\Column(type: 'text')]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $emailAddress = null;

    #[ORM\Column(length: 50)]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $idCopy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $powerOfAttorney = null;

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

    public function getCompanyNames(): array
    {
        return $this->companyNames;
    }

    public function setCompanyNames(array $companyNames): self
    {
        $this->companyNames = $companyNames;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    public function getIdNumber(): ?string
    {
        return $this->idNumber;
    }

    public function setIdNumber(string $idNumber): self
    {
        $this->idNumber = $idNumber;
        return $this;
    }

    public function getResidentialAddress(): ?string
    {
        return $this->residentialAddress;
    }

    public function setResidentialAddress(string $residentialAddress): self
    {
        $this->residentialAddress = $residentialAddress;
        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(string $companyAddress): self
    {
        $this->companyAddress = $companyAddress;
        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getIdCopy(): ?string
    {
        return $this->idCopy;
    }

    public function setIdCopy(?string $idCopy): self
    {
        $this->idCopy = $idCopy;
        return $this;
    }

    public function getPowerOfAttorney(): ?string
    {
        return $this->powerOfAttorney;
    }

    public function setPowerOfAttorney(?string $powerOfAttorney): self
    {
        $this->powerOfAttorney = $powerOfAttorney;
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
} 