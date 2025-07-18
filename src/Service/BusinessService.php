<?php

namespace App\Service;

use App\Entity\Business;
use App\Entity\Company;
use App\Repository\BusinessRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

class BusinessService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessRepository $businessRepository,
        private CompanyRepository $companyRepository
    ) {}

    public function createBusiness(array $data): Business
    {
        $business = new Business();
        $business->setBusinessName($data['business_name']);
        $business->setBusinessUid($data['business_uid']);
        $business->setDomain($data['domain'] ?? null);
        $business->setEmail($data['email'] ?? null);
        $business->setContactNumber($data['contact_number'] ?? null);
        $business->setAddress($data['address'] ?? null);

        // Set VAT number if provided
        if (isset($data['vat_number'])) {
            $business->setVatNumber($data['vat_number']);
        }

        // Set the company relationship
        if (isset($data['company_id'])) {
            $company = $this->companyRepository->find($data['company_id']);
            if ($company) {
                $business->setCompany($company);
            }
        }

        $this->entityManager->persist($business);
        $this->entityManager->flush();

        return $business;
    }

    public function getBusinessById(int $id): ?Business
    {
        return $this->businessRepository->find($id);
    }

    public function getAllBusinesses(): array
    {
        return $this->businessRepository->findAll();
    }

    public function getBusinessesByCompany(int $companyId): array
    {
        $company = $this->companyRepository->find($companyId);
        if (!$company) {
            return [];
        }
        
        return $company->getBusinesses()->toArray();
    }

    public function updateBusiness(string $uid, array $data): ?Business
    {
        $business = $this->businessRepository->findOneBy(['businessUid' => $uid]);
        
        if (!$business) {
            return null;
        }

        if (isset($data['business_name'])) {
            $business->setBusinessName($data['business_name']);
        }
        if (isset($data['domain'])) {
            $business->setDomain($data['domain']);
        }
        if (isset($data['email'])) {
            $business->setEmail($data['email']);
        }
        if (isset($data['contact_number'])) {
            $business->setContactNumber($data['contact_number']);
        }
        if (isset($data['address'])) {
            $business->setAddress($data['address']);
        }
        if (isset($data['vat_number'])) {
            $business->setVatNumber($data['vat_number']);
        }
        if (isset($data['company_id'])) {
            $company = $this->companyRepository->find($data['company_id']);
            if ($company) {
                $business->setCompany($company);
            }
        }

        $this->entityManager->flush();

        return $business;
    }

    public function deleteBusiness(int $id): bool
    {
        $business = $this->businessRepository->find($id);
        
        if (!$business) {
            return false;
        }

        $this->entityManager->remove($business);
        $this->entityManager->flush();

        return true;
    }

    public function getBusinessByUid(string $businessUid): ?Business
    {
        return $this->businessRepository->findOneBy(['businessUid' => $businessUid]);
    }
} 