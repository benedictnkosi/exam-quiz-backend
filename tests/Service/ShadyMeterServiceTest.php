<?php

namespace App\Tests\Service;

use App\Service\ShadyMeterService;
use App\Repository\NewsRepository;
use App\Repository\PoliticianRepository;
use App\Repository\PoliticianScandalRepository;
use App\Entity\PoliticianScandal;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ShadyMeterServiceTest extends TestCase
{
    private ShadyMeterService $shadyMeterService;
    private NewsRepository $newsRepository;
    private PoliticianRepository $politicianRepository;
    private PoliticianScandalRepository $scandalRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->newsRepository = $this->createMock(NewsRepository::class);
        $this->politicianRepository = $this->createMock(PoliticianRepository::class);
        $this->scandalRepository = $this->createMock(PoliticianScandalRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->shadyMeterService = new ShadyMeterService(
            $this->newsRepository,
            $this->politicianRepository,
            $this->scandalRepository,
            'test-api-key',
            $this->logger
        );
    }

    public function testGenerateScandalArticleReturnsCorrectStructure(): void
    {
        // Mock the scandal entity
        $scandalEntity = $this->createMock(PoliticianScandal::class);
        $scandalEntity->method('getScandals')->willReturn([
            [
                'title' => 'Test Scandal',
                'year' => '2023',
                'description' => 'A test scandal description'
            ]
        ]);
        $scandalEntity->method('setScandals')->willReturnSelf();
        $scandalEntity->method('setUpdatedAt')->willReturnSelf();

        $this->scandalRepository
            ->expects($this->once())
            ->method('findByPoliticianAndCountry')
            ->with('John Doe', 'Test Country')
            ->willReturn($scandalEntity);

        $this->scandalRepository
            ->expects($this->once())
            ->method('save')
            ->with($scandalEntity, true);

        // Test data
        $politician = 'John Doe';
        $country = 'Test Country';
        $scandal = [
            'title' => 'Test Scandal',
            'year' => '2023',
            'description' => 'A test scandal description'
        ];

        // Create a partial mock of the service to override the private method
        $partialMock = $this->getMockBuilder(ShadyMeterService::class)
            ->setConstructorArgs([
                $this->newsRepository,
                $this->politicianRepository,
                $this->scandalRepository,
                'test-api-key',
                $this->logger
            ])
            ->onlyMethods(['generateArticleWithOpenAI'])
            ->getMock();

        $expectedResponse = [
            'article' => 'This is a test article about the scandal.',
            'timeline' => [
                [
                    'date' => '2023-01-15',
                    'event' => 'Initial allegations surfaced'
                ],
                [
                    'date' => '2023-02-20',
                    'event' => 'Investigation launched'
                ]
            ],
            'involved_persons' => [
                [
                    'full_name' => 'John Doe',
                    'role' => 'Primary suspect',
                    'position' => 'Minister of Finance'
                ],
                [
                    'full_name' => 'Jane Smith',
                    'role' => 'Whistleblower',
                    'position' => 'Department Head'
                ]
            ]
        ];

        $partialMock->expects($this->once())
            ->method('generateArticleWithOpenAI')
            ->with($politician, $country, $scandal)
            ->willReturn($expectedResponse);

        // Test that the method returns the expected structure
        $result = $partialMock->generateScandalArticle($politician, $country, $scandal);

        // Verify the structure contains the expected fields
        $this->assertIsArray($result);
        $this->assertArrayHasKey('article', $result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('involved_persons', $result);
        $this->assertIsString($result['article']);
        $this->assertIsArray($result['timeline']);
        $this->assertIsArray($result['involved_persons']);
        
        // Verify the specific content
        $this->assertEquals($expectedResponse['article'], $result['article']);
        $this->assertEquals($expectedResponse['timeline'], $result['timeline']);
        $this->assertEquals($expectedResponse['involved_persons'], $result['involved_persons']);
    }

    public function testGenerateScandalArticleValidatesRequiredFields(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required fields: title and year are required');

        $this->shadyMeterService->generateScandalArticle('John Doe', 'Test Country', []);
    }

    public function testGenerateScandalArticleHandlesMissingScandalDocument(): void
    {
        $this->scandalRepository
            ->expects($this->once())
            ->method('findByPoliticianAndCountry')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No scandal document found for this politician');

        $scandal = [
            'title' => 'Test Scandal',
            'year' => '2023',
            'description' => 'A test scandal description'
        ];

        // Create a partial mock to avoid API calls
        $partialMock = $this->getMockBuilder(ShadyMeterService::class)
            ->setConstructorArgs([
                $this->newsRepository,
                $this->politicianRepository,
                $this->scandalRepository,
                'test-api-key',
                $this->logger
            ])
            ->onlyMethods(['generateArticleWithOpenAI'])
            ->getMock();

        $partialMock->method('generateArticleWithOpenAI')
            ->willReturn(['article' => 'test', 'timeline' => [], 'involved_persons' => []]);

        $partialMock->generateScandalArticle('John Doe', 'Test Country', $scandal);
    }

    public function testGenerateScandalArticleReturnsExistingArticleWithoutCallingAI(): void
    {
        // Mock the scandal entity with existing article
        $scandalEntity = $this->createMock(PoliticianScandal::class);
        $scandalEntity->method('getScandals')->willReturn([
            [
                'title' => 'Test Scandal',
                'year' => '2023',
                'description' => 'A test scandal description',
                'article' => 'This is an existing article about the scandal.',
                'timeline' => [
                    [
                        'date' => '2023-01-15',
                        'event' => 'Initial allegations surfaced'
                    ]
                ],
                'involved_persons' => [
                    [
                        'full_name' => 'John Doe',
                        'role' => 'Primary suspect',
                        'position' => 'Minister of Finance'
                    ]
                ]
            ]
        ]);

        $this->scandalRepository
            ->expects($this->once())
            ->method('findByPoliticianAndCountry')
            ->with('John Doe', 'Test Country')
            ->willReturn($scandalEntity);

        // Should NOT call save since we're returning existing article
        $this->scandalRepository
            ->expects($this->never())
            ->method('save');

        // Test data
        $politician = 'John Doe';
        $country = 'Test Country';
        $scandal = [
            'title' => 'Test Scandal',
            'year' => '2023',
            'description' => 'A test scandal description'
        ];

        // Create a partial mock of the service to ensure generateArticleWithOpenAI is NOT called
        $partialMock = $this->getMockBuilder(ShadyMeterService::class)
            ->setConstructorArgs([
                $this->newsRepository,
                $this->politicianRepository,
                $this->scandalRepository,
                'test-api-key',
                $this->logger
            ])
            ->onlyMethods(['generateArticleWithOpenAI'])
            ->getMock();

        // This method should NOT be called since article already exists
        $partialMock->expects($this->never())
            ->method('generateArticleWithOpenAI');

        // Test that the method returns the existing article
        $result = $partialMock->generateScandalArticle($politician, $country, $scandal);

        // Verify the structure contains the expected fields
        $this->assertIsArray($result);
        $this->assertArrayHasKey('article', $result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('involved_persons', $result);
        $this->assertIsString($result['article']);
        $this->assertIsArray($result['timeline']);
        $this->assertIsArray($result['involved_persons']);
        
        // Verify the specific content matches the existing article
        $this->assertEquals('This is an existing article about the scandal.', $result['article']);
        $this->assertCount(1, $result['timeline']);
        $this->assertCount(1, $result['involved_persons']);
    }

    public function testGetPoliticianConnections(): void
    {
        // Mock scandal entities with involved persons
        $scandalEntity1 = $this->createMock(PoliticianScandal::class);
        $scandalEntity1->method('getScandals')->willReturn([
            [
                'title' => 'State Capture Scandal',
                'year' => '2018',
                'description' => 'Corruption allegations involving state-owned enterprises',
                'involved_persons' => [
                    [
                        'full_name' => 'Cyril Ramaphosa',
                        'role' => 'Successor and investigator',
                        'position' => 'Deputy President'
                    ],
                    [
                        'full_name' => 'Nhlanhla Nene',
                        'role' => 'Finance Minister',
                        'position' => 'Minister of Finance'
                    ]
                ]
            ]
        ]);
        $scandalEntity1->method('getPolitician')->willReturn('Jacob Zuma');
        $scandalEntity1->method('getId')->willReturn(1);
        $scandalEntity1->method('getCountry')->willReturn('South Africa');

        $scandalEntity2 = $this->createMock(PoliticianScandal::class);
        $scandalEntity2->method('getScandals')->willReturn([
            [
                'title' => 'VBS Bank Scandal',
                'year' => '2019',
                'description' => 'Municipal funds misappropriation',
                'involved_persons' => [
                    [
                        'full_name' => 'Jacob Zuma',
                        'role' => 'Beneficiary',
                        'position' => 'Former President'
                    ]
                ]
            ]
        ]);
        $scandalEntity2->method('getPolitician')->willReturn('Floyd Shivambu');
        $scandalEntity2->method('getId')->willReturn(2);
        $scandalEntity2->method('getCountry')->willReturn('South Africa');

        $this->scandalRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$scandalEntity1, $scandalEntity2]);

        $result = $this->shadyMeterService->getPoliticianConnections();

        // Assert the structure and content
        $this->assertArrayHasKey('politician_id_filter', $result);
        $this->assertArrayHasKey('politician_name_filter', $result);
        $this->assertArrayHasKey('connections', $result);
        $this->assertArrayHasKey('total_connections', $result);
        $this->assertArrayHasKey('total_scandals_analyzed', $result);
        $this->assertArrayHasKey('message', $result);

        $this->assertNull($result['politician_id_filter']);
        $this->assertNull($result['politician_name_filter']);
        $this->assertEquals(2, $result['total_scandals_analyzed']);
        $this->assertGreaterThan(0, $result['total_connections']);

        // Check that connections are sorted by strength
        if (count($result['connections']) > 1) {
            $this->assertGreaterThanOrEqual(
                $result['connections'][1]['connection_strength'],
                $result['connections'][0]['connection_strength']
            );
        }

        // Check connection structure
        foreach ($result['connections'] as $connection) {
            $this->assertArrayHasKey('politician1', $connection);
            $this->assertArrayHasKey('politician2', $connection);
            $this->assertArrayHasKey('scandals', $connection);
            $this->assertArrayHasKey('connection_strength', $connection);
            $this->assertIsArray($connection['scandals']);
            $this->assertIsInt($connection['connection_strength']);
            
            // Check that scandals have country field
            foreach ($connection['scandals'] as $scandal) {
                $this->assertArrayHasKey('country', $scandal);
            }
        }
    }

    public function testGetPoliticianConnectionsWithPoliticianIdFilter(): void
    {
        // Mock politician entity
        $politicianEntity = $this->createMock(\App\Entity\Politician::class);
        $politicianEntity->method('getFullName')->willReturn('Jacob Zuma');

        // Mock scandal entities with involved persons
        $scandalEntity1 = $this->createMock(PoliticianScandal::class);
        $scandalEntity1->method('getScandals')->willReturn([
            [
                'title' => 'State Capture Scandal',
                'year' => '2018',
                'description' => 'Corruption allegations involving state-owned enterprises',
                'involved_persons' => [
                    [
                        'full_name' => 'Cyril Ramaphosa',
                        'role' => 'Successor and investigator',
                        'position' => 'Deputy President'
                    ]
                ]
            ]
        ]);
        $scandalEntity1->method('getPolitician')->willReturn('Jacob Zuma');
        $scandalEntity1->method('getId')->willReturn(1);
        $scandalEntity1->method('getCountry')->willReturn('South Africa');

        $this->politicianRepository
            ->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($politicianEntity);

        $this->scandalRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$scandalEntity1]);

        $result = $this->shadyMeterService->getPoliticianConnections(123);

        // Assert the structure and content
        $this->assertArrayHasKey('politician_id_filter', $result);
        $this->assertArrayHasKey('politician_name_filter', $result);
        $this->assertArrayHasKey('connections', $result);
        $this->assertArrayHasKey('total_connections', $result);
        $this->assertArrayHasKey('total_scandals_analyzed', $result);
        $this->assertArrayHasKey('message', $result);

        $this->assertEquals(123, $result['politician_id_filter']);
        $this->assertEquals('Jacob Zuma', $result['politician_name_filter']);
        $this->assertEquals(1, $result['total_scandals_analyzed']);
        $this->assertGreaterThan(0, $result['total_connections']);
        $this->assertStringContainsString('Jacob Zuma', $result['message']);
    }

    public function testGetPoliticianConnectionsPoliticianNotFound(): void
    {
        $this->politicianRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->shadyMeterService->getPoliticianConnections(999);

        // Assert the structure and content
        $this->assertArrayHasKey('politician_id_filter', $result);
        $this->assertArrayHasKey('politician_name_filter', $result);
        $this->assertArrayHasKey('connections', $result);
        $this->assertArrayHasKey('total_connections', $result);
        $this->assertArrayHasKey('message', $result);

        $this->assertEquals(999, $result['politician_id_filter']);
        $this->assertNull($result['politician_name_filter']);
        $this->assertEquals([], $result['connections']);
        $this->assertEquals(0, $result['total_connections']);
        $this->assertStringContainsString('Politician not found with ID: 999', $result['message']);
    }

    public function testGetPoliticianConnectionsNoScandals(): void
    {
        $this->scandalRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->shadyMeterService->getPoliticianConnections();

        $this->assertNull($result['politician_id_filter']);
        $this->assertNull($result['politician_name_filter']);
        $this->assertEquals([], $result['connections']);
        $this->assertEquals(0, $result['total_connections']);
        $this->assertEquals('No scandals found in the database', $result['message']);
    }
} 