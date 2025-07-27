<?php

namespace App\Tests\Controller;

use App\Service\ShadyMeterService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class PoliticianControllerTest extends WebTestCase
{
    private $client;
    private $shadyMeterService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->shadyMeterService = $this->createMock(ShadyMeterService::class);
        
        // Replace the service in the container
        self::getContainer()->set(ShadyMeterService::class, $this->shadyMeterService);
    }

    public function testGetPoliticianConnectionsSuccess(): void
    {
        // Mock the expected response
        $expectedResponse = [
            'politician_id_filter' => null,
            'politician_name_filter' => null,
            'connections' => [
                [
                    'politician1' => 'Jacob Zuma',
                    'politician2' => 'Cyril Ramaphosa',
                    'scandals' => [
                        [
                            'title' => 'State Capture Scandal',
                            'year' => '2018',
                            'description' => 'Corruption allegations involving state-owned enterprises',
                            'main_politician' => 'Jacob Zuma',
                            'involved_person' => 'Cyril Ramaphosa',
                            'role' => 'Successor and investigator',
                            'position' => 'Deputy President',
                            'scandal_id' => 1,
                            'country' => 'South Africa'
                        ]
                    ],
                    'connection_strength' => 1
                ]
            ],
            'total_connections' => 1,
            'total_scandals_analyzed' => 5,
            'message' => 'Connections found based on scandal involvement'
        ];

        $this->shadyMeterService
            ->expects($this->once())
            ->method('getPoliticianConnections')
            ->with(null)
            ->willReturn($expectedResponse);

        // Make the request
        $this->client->request('GET', '/api/politicians/connections');

        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($expectedResponse, $responseData);
    }

    public function testGetPoliticianConnectionsWithPoliticianIdFilter(): void
    {
        // Mock the expected response for filtered politician
        $expectedResponse = [
            'politician_id_filter' => 123,
            'politician_name_filter' => 'Jacob Zuma',
            'connections' => [
                [
                    'politician1' => 'Jacob Zuma',
                    'politician2' => 'Cyril Ramaphosa',
                    'scandals' => [
                        [
                            'title' => 'State Capture Scandal',
                            'year' => '2018',
                            'description' => 'Corruption allegations involving state-owned enterprises',
                            'main_politician' => 'Jacob Zuma',
                            'involved_person' => 'Cyril Ramaphosa',
                            'role' => 'Successor and investigator',
                            'position' => 'Deputy President',
                            'scandal_id' => 1,
                            'country' => 'South Africa'
                        ]
                    ],
                    'connection_strength' => 1
                ]
            ],
            'total_connections' => 1,
            'total_scandals_analyzed' => 5,
            'message' => 'Connections found involving Jacob Zuma'
        ];

        $this->shadyMeterService
            ->expects($this->once())
            ->method('getPoliticianConnections')
            ->with(123)
            ->willReturn($expectedResponse);

        // Make the request
        $this->client->request('GET', '/api/politicians/connections?politician_id=123');

        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($expectedResponse, $responseData);
    }

    public function testGetPoliticianConnectionsNoScandals(): void
    {
        // Mock the response for no scandals
        $expectedResponse = [
            'politician_id_filter' => null,
            'politician_name_filter' => null,
            'connections' => [],
            'total_connections' => 0,
            'message' => 'No scandals found in the database'
        ];

        $this->shadyMeterService
            ->expects($this->once())
            ->method('getPoliticianConnections')
            ->with(null)
            ->willReturn($expectedResponse);

        // Make the request
        $this->client->request('GET', '/api/politicians/connections');

        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($expectedResponse, $responseData);
    }
} 