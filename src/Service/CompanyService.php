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
        $company = new Company();
        $company->setCompanyNames($data['company_names'] ?? []);
        $company->setFullName($data['full_name']);
        $company->setSurname($data['surname']);
        $company->setIdNumber($data['id_number']);
        $company->setResidentialAddress($data['residential_address']);
        $company->setCompanyAddress($data['company_address']);
        $company->setEmailAddress($data['email_address']);
        $company->setPhoneNumber($data['phone_number']);
        $company->setIdCopy($data['id_copy'] ?? null);
        $company->setPowerOfAttorney($data['power_of_attorney'] ?? null);

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

        if (isset($data['company_names'])) {
            $company->setCompanyNames($data['company_names']);
        }
        if (isset($data['full_name'])) {
            $company->setFullName($data['full_name']);
        }
        if (isset($data['surname'])) {
            $company->setSurname($data['surname']);
        }
        if (isset($data['id_number'])) {
            $company->setIdNumber($data['id_number']);
        }
        if (isset($data['residential_address'])) {
            $company->setResidentialAddress($data['residential_address']);
        }
        if (isset($data['company_address'])) {
            $company->setCompanyAddress($data['company_address']);
        }
        if (isset($data['email_address'])) {
            $company->setEmailAddress($data['email_address']);
        }
        if (isset($data['phone_number'])) {
            $company->setPhoneNumber($data['phone_number']);
        }
        if (isset($data['id_copy'])) {
            $company->setIdCopy($data['id_copy']);
        }
        if (isset($data['power_of_attorney'])) {
            $company->setPowerOfAttorney($data['power_of_attorney']);
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
} 