<?php

namespace App\Tests\Service;

use App\Entity\Tender;
use App\Repository\TenderRepository;
use App\Service\TenderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TenderServiceTest extends TestCase
{
    private TenderService $tenderService;
    private EntityManagerInterface $entityManager;
    private TenderRepository $tenderRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tenderRepository = $this->createMock(TenderRepository::class);
        
        $this->tenderService = new TenderService(
            $this->entityManager,
            $this->tenderRepository
        );
    }

    public function testValidateTenderDataWithValidData(): void
    {
        $validData = [
            'category' => 'Construction',
            'tenderDescription' => 'Building construction project',
            'tenderNumber' => 'TEN-2024-001',
            'organOfState' => 'Department of Public Works',
            'province' => 'Gauteng',
            'closingDate' => '2024-12-31 23:59:59',
            'placeWhereGoodsWorksOrServicesAreRequired' => 'Pretoria, Gauteng',
            'contactPerson' => 'John Doe',
            'email' => 'john.doe@example.com',
            'telephoneNumber' => '+27 12 345 6789'
        ];

        $errors = $this->tenderService->validateTenderData($validData);

        $this->assertEmpty($errors);
    }

    public function testValidateTenderDataWithMissingRequiredFields(): void
    {
        $invalidData = [
            'category' => 'Construction',
            // Missing other required fields
        ];

        $errors = $this->tenderService->validateTenderData($invalidData);

        $this->assertNotEmpty($errors);
        $this->assertContains("Field 'tenderDescription' is required", $errors);
        $this->assertContains("Field 'tenderNumber' is required", $errors);
    }

    public function testValidateTenderDataWithInvalidEmail(): void
    {
        $invalidData = [
            'category' => 'Construction',
            'tenderDescription' => 'Building construction project',
            'tenderNumber' => 'TEN-2024-001',
            'organOfState' => 'Department of Public Works',
            'province' => 'Gauteng',
            'closingDate' => '2024-12-31 23:59:59',
            'placeWhereGoodsWorksOrServicesAreRequired' => 'Pretoria, Gauteng',
            'contactPerson' => 'John Doe',
            'email' => 'invalid-email',
            'telephoneNumber' => '+27 12 345 6789'
        ];

        $errors = $this->tenderService->validateTenderData($invalidData);

        $this->assertContains("Invalid email format", $errors);
    }

    public function testValidateTenderDataWithInvalidDate(): void
    {
        $invalidData = [
            'category' => 'Construction',
            'tenderDescription' => 'Building construction project',
            'tenderNumber' => 'TEN-2024-001',
            'organOfState' => 'Department of Public Works',
            'province' => 'Gauteng',
            'closingDate' => 'invalid-date',
            'placeWhereGoodsWorksOrServicesAreRequired' => 'Pretoria, Gauteng',
            'contactPerson' => 'John Doe',
            'email' => 'john.doe@example.com',
            'telephoneNumber' => '+27 12 345 6789'
        ];

        $errors = $this->tenderService->validateTenderData($invalidData);

        $this->assertContains("Invalid date format for 'closingDate'", $errors);
    }

    public function testGetTenderById(): void
    {
        $expectedTender = new Tender();
        $expectedTender->setCategory('Construction');
        $expectedTender->setTenderDescription('Test tender');
        $expectedTender->setTenderNumber('TEN-2024-001');

        $this->tenderRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($expectedTender);

        $result = $this->tenderService->getTenderById(1);

        $this->assertSame($expectedTender, $result);
    }

    public function testGetTenderByNumber(): void
    {
        $expectedTender = new Tender();
        $expectedTender->setTenderNumber('TEN-2024-001');

        $this->tenderRepository
            ->expects($this->once())
            ->method('findByTenderNumber')
            ->with('TEN-2024-001')
            ->willReturn($expectedTender);

        $result = $this->tenderService->getTenderByNumber('TEN-2024-001');

        $this->assertSame($expectedTender, $result);
    }
} 