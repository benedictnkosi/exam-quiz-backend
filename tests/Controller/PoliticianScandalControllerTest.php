<?php

namespace App\Tests\Controller;

use App\Controller\PoliticianScandalController;
use App\Entity\PoliticianScandal;
use App\Service\ShadyMeterService;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PoliticianScandalControllerTest extends WebTestCase
{
    private PoliticianScandalController $controller;
    private ShadyMeterService $shadyMeterService;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->shadyMeterService = $this->createMock(ShadyMeterService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        
        $this->controller = new PoliticianScandalController(
            $this->shadyMeterService,
            $this->serializer
        );
        
        // Set up the container for AbstractController
        $container = $this->getContainer();
        $this->controller->setContainer($container);
    }

    public function testGenerateScandalArticleWithValidData(): void
    {
        // Mock the service response
        $expectedResult = [
            'article' => 'Test article content',
            'timeline' => [],
            'involved_persons' => []
        ];

        $this->shadyMeterService
            ->expects($this->once())
            ->method('generateScandalArticle')
            ->with('John Doe', 'Test Country', [
                'title' => 'Test Scandal',
                'year' => '2023',
                'description' => 'A test scandal description'
            ])
            ->willReturn($expectedResult);

        // Mock the getPoliticianScandals response
        $scandalEntity = $this->createMock(PoliticianScandal::class);
        $scandalEntity->method('getCountry')->willReturn('Test Country');
        $scandalEntity->method('getScandals')->willReturn([
            [
                'title' => 'Test Scandal',
                'year' => '2023',
                'description' => 'A test scandal description',
                'article' => 'Test article content'
            ]
        ]);

        $this->shadyMeterService
            ->expects($this->once())
            ->method('getPoliticianScandals')
            ->with('John Doe')
            ->willReturn([$scandalEntity]);

        // Create request
        $request = new Request();
        $request->initialize([], [], [], [], [], [], json_encode([
            'politician' => 'John Doe',
            'country' => 'Test Country',
            'scandal' => [
                'title' => 'Test Scandal',
                'year' => '2023',
                'description' => 'A test scandal description'
            ]
        ]));

        // Execute the method
        $response = $this->controller->generateScandalArticle($request);

        // Assert response
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('article', $responseData);
        $this->assertArrayHasKey('timeline', $responseData);
        $this->assertArrayHasKey('involved_persons', $responseData);
        $this->assertArrayHasKey('cached', $responseData);
        $this->assertEquals('Test article content', $responseData['article']);
        $this->assertTrue($responseData['cached']);
    }

    public function testGenerateScandalArticleWithMissingPolitician(): void
    {
        $request = new Request();
        $request->initialize([], [], [], [], [], [], json_encode([
            'country' => 'Test Country',
            'scandal' => [
                'title' => 'Test Scandal',
                'year' => '2023',
                'description' => 'A test scandal description'
            ]
        ]));

        $response = $this->controller->generateScandalArticle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Missing required fields', $responseData['error']);
    }

    public function testGenerateScandalArticleWithMissingScandal(): void
    {
        $request = new Request();
        $request->initialize([], [], [], [], [], [], json_encode([
            'politician' => 'John Doe',
            'country' => 'Test Country'
        ]));

        $response = $this->controller->generateScandalArticle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Missing required fields', $responseData['error']);
    }
} 