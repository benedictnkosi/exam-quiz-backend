<?php

namespace App\Command;

use App\Entity\Commuter;
use App\Entity\DriverPassengerDistance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-dummy-commuters',
    description: 'Generates dummy commuters and commute distances for testing',
)]
class GenerateDummyCommutersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of commuters to generate', 50)
            ->addOption('drivers-ratio', 'd', InputOption::VALUE_OPTIONAL, 'Ratio of drivers (0.0-1.0)', 0.3)
            ->addOption('with-distances', 'w', InputOption::VALUE_NONE, 'Generate commute distances between commuters')
            ->addOption('clear-existing', 'x', InputOption::VALUE_NONE, 'Clear existing commuters and distances before generating');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $driversRatio = (float) $input->getOption('drivers-ratio');
        $withDistances = $input->getOption('with-distances');
        $clearExisting = $input->getOption('clear-existing');

        if ($count <= 0) {
            $io->error('Count must be greater than 0');
            return Command::FAILURE;
        }

        if ($driversRatio < 0 || $driversRatio > 1) {
            $io->error('Drivers ratio must be between 0.0 and 1.0');
            return Command::FAILURE;
        }

        $io->title('Generating Dummy Commuters and Distances');

        // Clear existing data if requested
        if ($clearExisting) {
            $io->section('Clearing existing data...');
            $this->clearExistingData();
            $io->success('Existing data cleared');
        }

        // Generate commuters
        $io->section('Generating commuters...');
        $commuters = $this->generateCommuters($count, $driversRatio);
        $io->success(sprintf('Generated %d commuters (%d drivers, %d passengers)', 
            count($commuters), 
            count(array_filter($commuters, fn($c) => $c->getType() === 'driver')),
            count(array_filter($commuters, fn($c) => $c->getType() === 'passenger'))
        ));

        // Generate route coordinates for drivers
        $io->section('Generating route coordinates for drivers...');
        $routesGenerated = $this->generateRouteCoordinates($commuters);
        $io->success(sprintf('Generated route coordinates for %d drivers', $routesGenerated));

        // Generate distances if requested
        if ($withDistances) {
            $io->section('Generating commute distances...');
            $distancesCount = $this->generateCommuteDistances($commuters);
            $io->success(sprintf('Generated %d commute distances', $distancesCount));
        }

        $io->success('Dummy data generation completed successfully!');

        return Command::SUCCESS;
    }

    private function clearExistingData(): void
    {
        $connection = $this->entityManager->getConnection();
        
        // Clear commute distances first (due to foreign key constraints)
        $connection->executeStatement('DELETE FROM commute_distances');
        
        // Clear commuters
        $connection->executeStatement('DELETE FROM commuters');
    }

    private function generateCommuters(int $count, float $driversRatio): array
    {
        $commuters = [];
        $driversCount = (int) ($count * $driversRatio);
        $passengersCount = $count - $driversCount;

        // Johannesburg area with coordinates around the specified locations
        $locations = [
            // Johannesburg Central (around home coordinates: -26.19326440, 28.09080250)
            [
                'cities' => ['Johannesburg', 'Braamfontein', 'Hillbrow', 'Newtown', 'Marshalltown'],
                'province' => 'Gauteng',
                'lat_range' => [-26.25, -26.15],
                'lng_range' => [28.05, 28.15]
            ],
            // Sandton/Northern Johannesburg (around work coordinates: -26.10542700, 28.04568390)
            [
                'cities' => ['Sandton', 'Rosebank', 'Melville', 'Parktown', 'Bryanston'],
                'province' => 'Gauteng',
                'lat_range' => [-26.15, -26.05],
                'lng_range' => [28.00, 28.10]
            ],
            // Midrand area
            [
                'cities' => ['Midrand', 'Centurion', 'Randburg', 'Fourways', 'Dainfern'],
                'province' => 'Gauteng',
                'lat_range' => [-26.10, -26.00],
                'lng_range' => [27.95, 28.05]
            ],
            // Roodepoort area
            [
                'cities' => ['Roodepoort', 'Northcliff', 'Fairland', 'Weltevreden Park', 'Constantia Kloof'],
                'province' => 'Gauteng',
                'lat_range' => [-26.20, -26.10],
                'lng_range' => [27.85, 27.95]
            ]
        ];

        // Common South African names
        $firstNames = [
            'John', 'Mary', 'David', 'Sarah', 'Michael', 'Lisa', 'James', 'Jennifer', 'Robert', 'Linda',
            'William', 'Patricia', 'Richard', 'Elizabeth', 'Joseph', 'Barbara', 'Thomas', 'Susan', 'Christopher', 'Jessica',
            'Charles', 'Sarah', 'Daniel', 'Karen', 'Matthew', 'Nancy', 'Anthony', 'Betty', 'Mark', 'Helen',
            'Donald', 'Sandra', 'Steven', 'Donna', 'Paul', 'Carol', 'Andrew', 'Ruth', 'Joshua', 'Sharon',
            'Kenneth', 'Michelle', 'Kevin', 'Laura', 'Brian', 'Emily', 'George', 'Kimberly', 'Edward', 'Deborah',
            'Ronald', 'Dorothy', 'Timothy', 'Lisa', 'Jason', 'Nancy', 'Jeffrey', 'Karen', 'Ryan', 'Betty',
            'Jacob', 'Helen', 'Gary', 'Sandra', 'Nicholas', 'Donna', 'Eric', 'Carol', 'Jonathan', 'Ruth',
            'Stephen', 'Julie', 'Larry', 'Joyce', 'Justin', 'Virginia', 'Scott', 'Victoria', 'Brandon', 'Kelly',
            'Benjamin', 'Lauren', 'Samuel', 'Christine', 'Frank', 'Amanda', 'Gregory', 'Melissa', 'Raymond', 'Debra'
        ];

        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
            'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
            'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
            'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts',
            'Gomez', 'Phillips', 'Evans', 'Turner', 'Diaz', 'Parker', 'Cruz', 'Edwards', 'Collins', 'Reyes',
            'Stewart', 'Morris', 'Morales', 'Murphy', 'Cook', 'Rogers', 'Gutierrez', 'Ortiz', 'Morgan', 'Cooper',
            'Peterson', 'Bailey', 'Reed', 'Kelly', 'Howard', 'Ramos', 'Kim', 'Cox', 'Ward', 'Richardson',
            'Watson', 'Brooks', 'Chavez', 'Wood', 'James', 'Bennett', 'Gray', 'Mendoza', 'Ruiz', 'Hughes'
        ];

        // Street names
        $streetNames = [
            'Main Street', 'Oak Avenue', 'Pine Road', 'Elm Street', 'Maple Drive', 'Cedar Lane', 'Birch Court',
            'Willow Way', 'Spruce Street', 'Cherry Avenue', 'Poplar Road', 'Sycamore Drive', 'Ash Lane',
            'Beech Court', 'Cypress Way', 'Magnolia Street', 'Dogwood Avenue', 'Redwood Road', 'Sequoia Drive',
            'Juniper Lane', 'Fir Court', 'Hemlock Way', 'Larch Street', 'Alder Avenue', 'Hazel Road',
            'Walnut Drive', 'Pecan Lane', 'Hickory Court', 'Chestnut Way', 'Acorn Street', 'Beech Avenue',
            'Oak Road', 'Maple Drive', 'Pine Lane', 'Elm Court', 'Cedar Way', 'Birch Street', 'Willow Avenue',
            'Spruce Road', 'Cherry Drive', 'Poplar Lane', 'Sycamore Court', 'Ash Way', 'Magnolia Street',
            'Dogwood Avenue', 'Redwood Road', 'Sequoia Drive', 'Juniper Lane', 'Fir Court', 'Hemlock Way'
        ];

        // Generate drivers
        for ($i = 0; $i < $driversCount; $i++) {
            $commuter = $this->createCommuter($firstNames, $lastNames, $locations, $streetNames, 'driver', $i);
            $commuters[] = $commuter;
            $this->entityManager->persist($commuter);
        }

        // Generate passengers
        for ($i = 0; $i < $passengersCount; $i++) {
            $commuter = $this->createCommuter($firstNames, $lastNames, $locations, $streetNames, 'passenger', $i + $driversCount);
            $commuters[] = $commuter;
            $this->entityManager->persist($commuter);
        }

        $this->entityManager->flush();
        return $commuters;
    }

    private function generateRouteCoordinates(array $commuters): int
    {
        $routesGenerated = 0;
        $drivers = array_filter($commuters, fn($c) => $c->getType() === 'driver');
        
        foreach ($drivers as $driver) {
            // Check if driver has coordinates
            if ($driver->getHomeLat() === null || $driver->getHomeLng() === null || 
                $driver->getWorkLat() === null || $driver->getWorkLng() === null) {
                continue;
            }
            
            // Always generate dummy route coordinates (no Google API dependency)
            $routeCoordinates = $this->generateSimpleRouteCoordinates(
                $driver->getHomeLat(),
                $driver->getHomeLng(),
                $driver->getWorkLat(),
                $driver->getWorkLng()
            );
            $driver->setRouteCoordinates($routeCoordinates);
            $routesGenerated++;
        }
        
        $this->entityManager->flush();
        return $routesGenerated;
    }

    private function generateSimpleRouteCoordinates(float $homeLat, float $homeLng, float $workLat, float $workLng): array
    {
        // Generate a realistic route with 15 points that follows roads
        $coordinates = [];
        $numPoints = 15;
        
        // Calculate the direct distance and direction
        $latDiff = $workLat - $homeLat;
        $lngDiff = $workLng - $homeLng;
        $distance = sqrt($latDiff * $latDiff + $lngDiff * $lngDiff);
        
        // Create a route that follows a more realistic path (not straight line)
        for ($i = 0; $i < $numPoints; $i++) {
            $ratio = $i / ($numPoints - 1);
            
            // Base linear interpolation
            $baseLat = $homeLat + $latDiff * $ratio;
            $baseLng = $homeLng + $lngDiff * $ratio;
            
            // Add realistic road-like variations
            $variation = $this->generateRoadVariation($ratio, $distance);
            
            $lat = $baseLat + $variation['lat'];
            $lng = $baseLng + $variation['lng'];
            
            $coordinates[] = [
                'lat' => round($lat, 8),
                'lng' => round($lng, 8)
            ];
        }
        
        return $coordinates;
    }
    
    private function generateRoadVariation(float $ratio, float $distance): array
    {
        // Create realistic road variations that simulate following streets
        $variation = 0.0;
        
        // Add some "turns" at certain points to simulate road changes
        if ($ratio > 0.2 && $ratio < 0.4) {
            // First "turn" - slight curve
            $variation = sin(($ratio - 0.2) * 10) * 0.002;
        } elseif ($ratio > 0.6 && $ratio < 0.8) {
            // Second "turn" - another curve
            $variation = sin(($ratio - 0.6) * 10) * 0.0015;
        }
        
        // Add some random road-like variations
        $randomVariation = (rand(-50, 50) / 100000); // ±0.0005 degrees
        
        // Scale variation based on distance
        $scaledVariation = ($variation + $randomVariation) * min($distance * 10, 1.0);
        
        return [
            'lat' => $scaledVariation,
            'lng' => $scaledVariation * 0.8 // Slightly different for longitude
        ];
    }

    private function createCommuter(array $firstNames, array $lastNames, array $locations, array $streetNames, string $type, int $index): Commuter
    {
        $commuter = new Commuter();
        
        // Generate name
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $commuter->setName($firstName . ' ' . $lastName);
        
        // Generate phone number (South African format)
        $phoneNumber = '+27' . rand(60, 89) . rand(1000000, 9999999);
        $commuter->setPhoneNumber($phoneNumber);
        
        // Generate coordinates around the specified locations
        // Home coordinates: -26.19326440, 28.09080250
        // Work coordinates: -26.10542700, 28.04568390
        
        // Generate home coordinates (around -26.19326440, 28.09080250)
        $homeLat = $this->randomFloat(-26.25, -26.15); // ±0.06 degrees (~6.7 km radius)
        $homeLng = $this->randomFloat(28.05, 28.15);   // ±0.06 degrees (~6.7 km radius)
        
        // Generate work coordinates (around -26.10542700, 28.04568390)
        $workLat = $this->randomFloat(-26.15, -26.05); // ±0.06 degrees (~6.7 km radius)
        $workLng = $this->randomFloat(28.00, 28.10);   // ±0.06 degrees (~6.7 km radius)
        
        // Select appropriate cities based on coordinates
        $homeCity = $this->getCityForCoordinates($homeLat, $homeLng);
        $workCity = $this->getCityForCoordinates($workLat, $workLng);
        
        // Set addresses
        $homeStreet = rand(1, 999) . ' ' . $streetNames[array_rand($streetNames)];
        $workStreet = rand(1, 999) . ' ' . $streetNames[array_rand($streetNames)];
        
        $commuter->setHomeAddressStreet($homeStreet);
        $commuter->setHomeCity($homeCity);
        $commuter->setHomeProvince('Gauteng');
        $commuter->setHomeLat($homeLat);
        $commuter->setHomeLng($homeLng);
        
        $commuter->setWorkAddressStreet($workStreet);
        $commuter->setWorkCity($workCity);
        $commuter->setWorkProvince('Gauteng');
        $commuter->setWorkLat($workLat);
        $commuter->setWorkLng($workLng);
        
        $commuter->setType($type);
        $commuter->setStatus('active');
        
        // Random subscription (30% chance)
        if (rand(1, 100) <= 30) {
            $subscriptions = ['premium', 'basic', 'pro'];
            $commuter->setSubscription($subscriptions[array_rand($subscriptions)]);
        }
        
        // Random push notification token (70% chance)
        if (rand(1, 100) <= 70) {
            $commuter->setPushNotificationToken('fcm_token_' . bin2hex(random_bytes(16)));
        }
        
        return $commuter;
    }

    private function generateCommuteDistances(array $commuters): int
    {
        $distancesCount = 0;
        $drivers = array_filter($commuters, fn($c) => $c->getType() === 'driver');
        $passengers = array_filter($commuters, fn($c) => $c->getType() === 'passenger');
        
        if (empty($drivers) || empty($passengers)) {
            return 0;
        }
        
        // Generate distances for each driver-passenger combination
        foreach ($drivers as $driver) {
            foreach ($passengers as $passenger) {
                // Skip if same person
                if ($driver->getId() === $passenger->getId()) {
                    continue;
                }
                
                // 70% chance to create a distance record
                if (rand(1, 100) <= 70) {
                    $distance = $this->createCommuteDistance($driver, $passenger);
                    $this->entityManager->persist($distance);
                    $distancesCount++;
                }
            }
        }
        
        $this->entityManager->flush();
        return $distancesCount;
    }

    private function createCommuteDistance(Commuter $driver, Commuter $passenger): DriverPassengerDistance
    {
        $distance = new DriverPassengerDistance();
        
        $distance->setDriverCommuteId($driver->getId());
        $distance->setPassengerCommuteId($passenger->getId());
        
        // Calculate realistic distances using Haversine formula
        $homeDistance = $this->calculateDistance(
            $driver->getHomeLat(), $driver->getHomeLng(),
            $passenger->getHomeLat(), $passenger->getHomeLng()
        );
        
        $workDistance = $this->calculateDistance(
            $driver->getWorkLat(), $driver->getWorkLng(),
            $passenger->getWorkLat(), $passenger->getWorkLng()
        );
        
        $distance->setHomeDistance($homeDistance);
        $distance->setWorkDistance($workDistance);
        $distance->setMaxDistance(max($homeDistance, $workDistance));
        
        // Set random calculated time (within last 30 days)
        $calculatedAt = new \DateTime();
        $calculatedAt->modify('-' . rand(0, 30) . ' days');
        $calculatedAt->modify('-' . rand(0, 23) . ' hours');
        $calculatedAt->modify('-' . rand(0, 59) . ' minutes');
        $distance->setCalculatedAt($calculatedAt);
        
        $distance->setStatus('active');
        
        return $distance;
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Haversine formula to calculate distance between two points
        $earthRadius = 6371000; // Earth's radius in meters
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c; // Distance in meters
    }

    private function getCityForCoordinates(float $lat, float $lng): string
    {
        // Define city boundaries based on coordinates
        if ($lat >= -26.15 && $lat <= -26.05 && $lng >= 28.00 && $lng <= 28.10) {
            // Sandton area
            $cities = ['Sandton', 'Rosebank', 'Melville', 'Parktown', 'Bryanston'];
        } elseif ($lat >= -26.25 && $lat <= -26.15 && $lng >= 28.05 && $lng <= 28.15) {
            // Johannesburg Central area
            $cities = ['Johannesburg', 'Braamfontein', 'Hillbrow', 'Newtown', 'Marshalltown'];
        } elseif ($lat >= -26.20 && $lat <= -26.10 && $lng >= 27.85 && $lng <= 27.95) {
            // Roodepoort area
            $cities = ['Roodepoort', 'Northcliff', 'Fairland', 'Weltevreden Park', 'Constantia Kloof'];
        } elseif ($lat >= -26.10 && $lat <= -26.00 && $lng >= 27.95 && $lng <= 28.05) {
            // Midrand area
            $cities = ['Midrand', 'Centurion', 'Randburg', 'Fourways', 'Dainfern'];
        } else {
            // Default to Johannesburg area
            $cities = ['Johannesburg', 'Sandton', 'Rosebank', 'Melville', 'Parktown'];
        }
        
        return $cities[array_rand($cities)];
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + (($max - $min) * (mt_rand() / mt_getrandmax()));
    }
} 