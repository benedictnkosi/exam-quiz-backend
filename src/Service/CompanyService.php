<?php

namespace App\Service;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

class CompanyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompanyRepository $companyRepository
    ) {}

    public function createCompany(array $data): Company
    {
        // Check if company with this enterprise number already exists
        $existingCompany = $this->getCompanyByEnterpriseNumber($data['enterprise_number']);
        if ($existingCompany) {
            throw new \Exception("Company with enterprise number '{$data['enterprise_number']}' already exists");
        }

        $company = new Company();
        $company->setEnterpriseNumber($data['enterprise_number']);
        $company->setEnterpriseName($data['enterprise_name']);
        $company->setEnterpriseType($data['enterprise_type']);
        $company->setEnterpriseStatus($data['enterprise_status']);
        $company->setComplianceNotice($data['compliance_notice'] ?? null);
        
        if (isset($data['registration_date'])) {
            $registrationDate = new \DateTime($data['registration_date']);
            $company->setRegistrationDate($registrationDate);
        }
        
        $company->setPhysicalAddress($data['physical_address']);
        $company->setPostalAddress($data['postal_address'] ?? null);
        $company->setStatus($data['status'] ?? 'active');

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $company;
    }

    public function getCompanyById(int $id): ?Company
    {
        return $this->companyRepository->find($id);
    }

    public function getAllCompanies(): array
    {
        return $this->companyRepository->findAll();
    }

    public function updateCompany(int $id, array $data): ?Company
    {
        $company = $this->companyRepository->find($id);
        
        if (!$company) {
            return null;
        }

        if (isset($data['enterprise_number'])) {
            $company->setEnterpriseNumber($data['enterprise_number']);
        }
        if (isset($data['enterprise_name'])) {
            $company->setEnterpriseName($data['enterprise_name']);
        }
        if (isset($data['enterprise_type'])) {
            $company->setEnterpriseType($data['enterprise_type']);
        }
        if (isset($data['enterprise_status'])) {
            $company->setEnterpriseStatus($data['enterprise_status']);
        }
        if (isset($data['compliance_notice'])) {
            $company->setComplianceNotice($data['compliance_notice']);
        }
        if (isset($data['registration_date'])) {
            $registrationDate = new \DateTime($data['registration_date']);
            $company->setRegistrationDate($registrationDate);
        }
        if (isset($data['physical_address'])) {
            $company->setPhysicalAddress($data['physical_address']);
        }
        if (isset($data['postal_address'])) {
            $company->setPostalAddress($data['postal_address']);
        }
        if (isset($data['status'])) {
            $company->setStatus($data['status']);
        }

        $this->entityManager->flush();

        return $company;
    }

    public function deleteCompany(int $id): bool
    {
        $company = $this->companyRepository->find($id);
        
        if (!$company) {
            return false;
        }

        $this->entityManager->remove($company);
        $this->entityManager->flush();

        return true;
    }

    public function getCompanyByEnterpriseNumber(string $enterpriseNumber): ?Company
    {
        return $this->companyRepository->findOneBy(['enterpriseNumber' => $enterpriseNumber]);
    }

    public function getCompaniesByEnterpriseType(string $enterpriseType): array
    {
        return $this->companyRepository->findBy(['enterpriseType' => $enterpriseType]);
    }

    public function getCompaniesByEnterpriseStatus(string $enterpriseStatus): array
    {
        return $this->companyRepository->findBy(['enterpriseStatus' => $enterpriseStatus]);
    }

    public function validateCompanyData(array $data): array
    {
        $errors = [];

        if (empty($data['enterprise_number'])) {
            $errors[] = "Enterprise number is required";
        }

        if (empty($data['enterprise_name'])) {
            $errors[] = "Enterprise name is required";
        }

        if (empty($data['enterprise_type'])) {
            $errors[] = "Enterprise type is required";
        }

        if (empty($data['enterprise_status'])) {
            $errors[] = "Enterprise status is required";
        }

        if (empty($data['physical_address'])) {
            $errors[] = "Physical address is required";
        }

        return $errors;
    }

    public function createOrUpdateCompany(array $data): Company
    {
        // Check if company with this enterprise number already exists
        $existingCompany = $this->getCompanyByEnterpriseNumber($data['enterprise_number']);
        
        if ($existingCompany) {
            // Update existing company
            if (isset($data['enterprise_name'])) {
                $existingCompany->setEnterpriseName($data['enterprise_name']);
            }
            if (isset($data['enterprise_type'])) {
                $existingCompany->setEnterpriseType($data['enterprise_type']);
            }
            if (isset($data['enterprise_status'])) {
                $existingCompany->setEnterpriseStatus($data['enterprise_status']);
            }
            if (isset($data['compliance_notice'])) {
                $existingCompany->setComplianceNotice($data['compliance_notice']);
            }
            if (isset($data['registration_date'])) {
                $registrationDate = new \DateTime($data['registration_date']);
                $existingCompany->setRegistrationDate($registrationDate);
            }
            if (isset($data['physical_address'])) {
                $existingCompany->setPhysicalAddress($data['physical_address']);
            }
            if (isset($data['postal_address'])) {
                $existingCompany->setPostalAddress($data['postal_address']);
            }
            if (isset($data['status'])) {
                $existingCompany->setStatus($data['status']);
            }

            $this->entityManager->flush();
            return $existingCompany;
        } else {
            // Create new company
            return $this->createCompany($data);
        }
    }
} 