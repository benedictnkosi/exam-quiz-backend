<?php

namespace App\Tests\Service;

use App\Service\GooglePlacesService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GooglePlacesServiceTest extends TestCase
{
    private GooglePlacesService $service;
    private $mockHttpClient;
    private $mockLogger;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Create service with test API key
        $this->service = new GooglePlacesService('test-api-key', $this->mockLogger);
    }

    public function testLookupAddressWithValidInput()
    {
        $addressPrefix = '123 Ma';
        
        // Mock response data
        $mockResponseData = [
            'status' => 'OK',
            'predictions' => [
                [
                    'place_id' => 'test_place_id_1',
                    'description' => '123 Main Street, Johannesburg, South Africa',
                    'structured_formatting' => [
                        'main_text' => '123 Main Street',
                        'secondary_text' => 'Johannesburg, South Africa'
                    ],
                    'types' => ['street_address'],
                    'matched_substrings' => [
                        ['length' => 5, 'offset' => 0]
                    ]
                ]
            ]
        ];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn(json_encode($mockResponseData));

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->service->getApiUrl(), $this->callback(function ($options) use ($addressPrefix) {
                return $options['query']['input'] === $addressPrefix &&
                       $options['query']['key'] === 'test-api-key';
            }))
            ->willReturn($mockResponse);

        $result = $this->service->lookupAddress($addressPrefix);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('test_place_id_1', $result[0]['place_id']);
        $this->assertEquals('123 Main Street, Johannesburg, South Africa', $result[0]['description']);
    }

    public function testLookupAddressWithInvalidInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address prefix must be at least 5 characters long');

        $this->service->lookupAddress('123');
    }

    public function testLookupAddressWithEmptyInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address prefix must be at least 5 characters long');

        $this->service->lookupAddress('');
    }

    public function testLookupAddressWithCountryCode()
    {
        $addressPrefix = '123 Ma';
        $countryCode = 'ZA';

        $mockResponseData = [
            'status' => 'OK',
            'predictions' => []
        ];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn(json_encode($mockResponseData));

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->service->getApiUrl(), $this->callback(function ($options) use ($addressPrefix, $countryCode) {
                return $options['query']['input'] === $addressPrefix &&
                       $options['query']['components'] === "country:{$countryCode}";
            }))
            ->willReturn($mockResponse);

        $result = $this->service->lookupAddress($addressPrefix, $countryCode);

        $this->assertIsArray($result);
    }

    public function testGetApiKey()
    {
        $this->assertEquals('test-api-key', $this->service->getApiKey());
    }

    public function testGetApiUrl()
    {
        $this->assertEquals('https://maps.googleapis.com/maps/api/place/autocomplete/json', $this->service->getApiUrl());
    }
} 