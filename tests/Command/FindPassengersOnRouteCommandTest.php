<?php

namespace App\Tests\Command;

use App\Entity\Commuter;
use App\Repository\CommuterRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FindPassengersOnRouteCommandTest extends KernelTestCase
{
    private CommuterRepository $commuterRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->commuterRepository = static::getContainer()->get(CommuterRepository::class);
    }

    public function testFindPassengersOnRoute(): void
    {
        // Create test driver with route coordinates
        $driver = new Commuter();
        $driver->setUid('driver-123');
        $driver->setName('Test Driver');
        $driver->setPhoneNumber('+1234567890');
        $driver->setType('driver');
        $driver->setHomeAddress('123 Main St');
        $driver->setHomeLatitude(-26.2041);
        $driver->setHomeLongitude(28.0473);
        $driver->setWorkAddress('456 Work Ave');
        $driver->setWorkLatitude(-26.2141);
        $driver->setWorkLongitude(28.0573);
        $driver->setRouteCoordinates([
            ['lat' => -26.2041, 'lng' => 28.0473],
            ['lat' => -26.2091, 'lng' => 28.0523],
            ['lat' => -26.2141, 'lng' => 28.0573]
        ]);

        // Create test passengers
        $passenger1 = new Commuter();
        $passenger1->setUid('passenger-1');
        $passenger1->setName('Passenger 1');
        $passenger1->setPhoneNumber('+1111111111');
        $passenger1->setType('passenger');
        $passenger1->setHomeAddress('Near Route');
        $passenger1->setHomeLatitude(-26.2091);
        $passenger1->setHomeLongitude(28.0523);

        $passenger2 = new Commuter();
        $passenger2->setUid('passenger-2');
        $passenger2->setName('Passenger 2');
        $passenger2->setPhoneNumber('+2222222222');
        $passenger2->setType('passenger');
        $passenger2->setHomeAddress('Far Away');
        $passenger2->setHomeLatitude(-26.3000);
        $passenger2->setHomeLongitude(28.1000);

        // Save entities
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($driver);
        $entityManager->persist($passenger1);
        $entityManager->persist($passenger2);
        $entityManager->flush();

        // Test the command
        $application = new Application(self::$kernel);
        $command = $application->find('app:find-passengers-on-route');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'driver-uid' => 'driver-123',
            '--max-distance' => 1000
        ]);

        $output = $commandTester->getDisplay();
        
        // Assert that passenger 1 (near route) is found
        $this->assertStringContainsString('passenger-1', $output);
        $this->assertStringContainsString('Passenger 1', $output);
        
        // Assert that passenger 2 (far away) is not found
        $this->assertStringNotContainsString('passenger-2', $output);
        $this->assertStringNotContainsString('Passenger 2', $output);

        // Clean up
        $entityManager->remove($driver);
        $entityManager->remove($passenger1);
        $entityManager->remove($passenger2);
        $entityManager->flush();
    }

    public function testDriverNotFound(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:find-passengers-on-route');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'driver-uid' => 'non-existent-driver'
        ]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('not found', $commandTester->getDisplay());
    }

    public function testDriverWithoutRoute(): void
    {
        // Create driver without route coordinates
        $driver = new Commuter();
        $driver->setUid('driver-no-route');
        $driver->setName('Driver No Route');
        $driver->setPhoneNumber('+1234567890');
        $driver->setType('driver');
        $driver->setHomeAddress('123 Main St');
        $driver->setHomeLatitude(-26.2041);
        $driver->setHomeLongitude(28.0473);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($driver);
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('app:find-passengers-on-route');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'driver-uid' => 'driver-no-route'
        ]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('has no route coordinates', $commandTester->getDisplay());

        // Clean up
        $entityManager->remove($driver);
        $entityManager->flush();
    }
} 