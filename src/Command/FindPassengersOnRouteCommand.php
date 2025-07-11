<?php

namespace App\Command;

use App\Entity\Commuter;
use App\Entity\DriverPassengerDistance;
use App\Repository\CommuterRepository;
use App\Repository\DriverPassengerDistanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:find-passengers-on-route',
    description: 'Find passengers that are on a driver\'s route',
)]
class FindPassengersOnRouteCommand extends Command
{
    public function __construct(
        private CommuterRepository $commuterRepository,
        private DriverPassengerDistanceRepository $distanceRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('driver-uid', InputArgument::OPTIONAL, 'The UID of the driver (optional - if not provided, processes all drivers)')
            ->addOption('max-distance', 'd', InputOption::VALUE_OPTIONAL, 'Maximum distance in meters from route (default: 1000)', 1000)
            ->addOption('output-format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: json, table, or csv (default: table)', 'table')
            ->addOption('output-file', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (for json/csv formats)')
            ->addOption('force-recalculate', null, InputOption::VALUE_NONE, 'Force recalculation of existing distances')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for processing (default: 100)', 100)
            ->addOption('show-stats', 's', InputOption::VALUE_NONE, 'Show statistics after processing')
            ->setHelp('This command finds passengers that are within a specified distance from driver routes. If no driver UID is provided, it processes all drivers.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $driverUid = $input->getArgument('driver-uid');
        $maxDistance = (int) $input->getOption('max-distance');
        $outputFormat = $input->getOption('output-format');
        $outputFile = $input->getOption('output-file');
        $forceRecalculate = $input->getOption('force-recalculate');
        $batchSize = (int) $input->getOption('batch-size');
        $showStats = $input->getOption('show-stats');

        if ($driverUid) {
            return $this->processSingleDriver($driverUid, $maxDistance, $outputFormat, $outputFile, $io);
        } else {
            return $this->processAllDrivers($maxDistance, $forceRecalculate, $batchSize, $showStats, $io);
        }
    }

    private function processSingleDriver(string $driverUid, int $maxDistance, string $outputFormat, ?string $outputFile, SymfonyStyle $io): int
    {
        $io->title("Finding passengers on driver route for UID: {$driverUid}");

        // Find the driver
        $driver = $this->commuterRepository->findOneBy(['uid' => $driverUid, 'type' => 'driver']);
        
        if (!$driver) {
            $io->error("Driver with UID '{$driverUid}' not found or is not a driver.");
            return Command::FAILURE;
        }

        if (!$driver->getRouteCoordinates()) {
            $io->error("Driver '{$driverUid}' has no route coordinates. Please save a route first.");
            return Command::FAILURE;
        }

        $io->info("Driver: {$driver->getName()} ({$driver->getPhoneNumber()})");
        $io->info("Route coordinates: " . count($driver->getRouteCoordinates()) . " points");
        $io->info("Maximum distance: {$maxDistance} meters");

        // Find all passengers
        $passengers = $this->commuterRepository->findBy(['type' => 'passenger']);
        
        if (empty($passengers)) {
            $io->warning("No passengers found in the system.");
            return Command::SUCCESS;
        }

        $io->info("Found " . count($passengers) . " passengers to check");

        $passengersOnRoute = [];
        $routeCoordinates = $driver->getRouteCoordinates();

        foreach ($passengers as $passenger) {
            $passengerHomeLat = $passenger->getHomeLat();
            $passengerHomeLng = $passenger->getHomeLng();
            $passengerWorkLat = $passenger->getWorkLat();
            $passengerWorkLng = $passenger->getWorkLng();

            if (!$passengerHomeLat || !$passengerHomeLng || !$passengerWorkLat || !$passengerWorkLng) {
                continue; // Skip passengers without both home and work coordinates
            }

            // Calculate distance from passenger's home to driver's route
            $homeDistance = $this->calculateMinDistanceToRoute(
                $passengerHomeLat,
                $passengerHomeLng,
                $routeCoordinates
            );

            // Calculate distance from passenger's work to driver's route
            $workDistance = $this->calculateMinDistanceToRoute(
                $passengerWorkLat,
                $passengerWorkLng,
                $routeCoordinates
            );

            // Calculate the maximum distance (both should be close to route)
            $maxDistanceValue = max($homeDistance, $workDistance);

            if ($maxDistanceValue <= $maxDistance) {
                $passengersOnRoute[] = [
                    'uid' => $passenger->getUid(),
                    'name' => $passenger->getName(),
                    'phone' => $passenger->getPhoneNumber(),
                    'home_address' => $passenger->getHomeAddressStreet(),
                    'work_address' => $passenger->getWorkAddressStreet(),
                    'home_lat' => $passengerHomeLat,
                    'home_lng' => $passengerHomeLng,
                    'work_lat' => $passengerWorkLat,
                    'work_lng' => $passengerWorkLng,
                    'home_distance' => round($homeDistance, 2),
                    'work_distance' => round($workDistance, 2),
                    'max_distance_to_route' => round($maxDistanceValue, 2),
                    'created_at' => $passenger->getCreated()->format('Y-m-d H:i:s')
                ];
            }
        }

        // Sort by distance
        usort($passengersOnRoute, function($a, $b) {
            return $a['distance_to_route'] <=> $b['distance_to_route'];
        });

        $io->success("Found " . count($passengersOnRoute) . " passengers on the route");

        // Output results
        switch ($outputFormat) {
            case 'json':
                $this->outputJson($passengersOnRoute, $outputFile, $io);
                break;
            case 'csv':
                $this->outputCsv($passengersOnRoute, $outputFile, $io);
                break;
            case 'table':
            default:
                $this->outputTable($passengersOnRoute, $io);
                break;
        }

        return Command::SUCCESS;
    }

    private function processAllDrivers(int $maxDistance, bool $forceRecalculate, int $batchSize, bool $showStats, SymfonyStyle $io): int
    {
        $io->title("Processing all drivers for passenger distance calculations");

        // Find all drivers with route coordinates
        $drivers = $this->commuterRepository->findBy(['type' => 'driver']);
        $driversWithRoutes = array_filter($drivers, function($driver) {
            return $driver->getRouteCoordinates() && !empty($driver->getRouteCoordinates());
        });

        if (empty($driversWithRoutes)) {
            $io->warning("No drivers with route coordinates found.");
            return Command::SUCCESS;
        }

        $io->info("Found " . count($driversWithRoutes) . " drivers with routes");

        // Find all passengers with both home and work coordinates
        $passengers = $this->commuterRepository->findBy(['type' => 'passenger']);
        $passengersWithCoords = array_filter($passengers, function($passenger) {
            return $passenger->getHomeLat() && $passenger->getHomeLng() && 
                   $passenger->getWorkLat() && $passenger->getWorkLng();
        });

        if (empty($passengersWithCoords)) {
            $io->warning("No passengers with coordinates found.");
            return Command::SUCCESS;
        }

        $io->info("Found " . count($passengersWithCoords) . " passengers with both home and work coordinates");

        $totalCalculations = 0;
        $skippedCalculations = 0;
        $newCalculations = 0;

        $progressBar = $io->createProgressBar(count($driversWithRoutes));
        $progressBar->start();

        foreach ($driversWithRoutes as $driver) {
            $driverUid = $driver->getUid();
            $routeCoordinates = $driver->getRouteCoordinates();

            foreach ($passengersWithCoords as $passenger) {
                $passengerUid = $passenger->getUid();

                // Check if calculation already exists
                if (!$forceRecalculate && $this->distanceRepository->existsByDriverAndPassenger($driver->getId(), $passenger->getId())) {
                    $skippedCalculations++;
                    continue;
                }

                // Calculate distance from passenger's home to driver's route
                $homeDistance = $this->calculateMinDistanceToRoute(
                    $passenger->getHomeLat(),
                    $passenger->getHomeLng(),
                    $routeCoordinates
                );

                // Calculate distance from passenger's work to driver's route
                $workDistance = $this->calculateMinDistanceToRoute(
                    $passenger->getWorkLat(),
                    $passenger->getWorkLng(),
                    $routeCoordinates
                );

                // Calculate the maximum distance (both should be close to route)
                $maxDistanceValue = max($homeDistance, $workDistance);

                // Save or update the distance calculation
                $distanceEntity = $this->distanceRepository->findByDriverAndPassenger($driver->getId(), $passenger->getId());
                
                if (!$distanceEntity) {
                    $distanceEntity = new DriverPassengerDistance();
                    $distanceEntity->setDriverCommuteId($driver->getId());
                    $distanceEntity->setPassengerCommuteId($passenger->getId());
                    $newCalculations++;
                }

                $distanceEntity->setHomeDistance($homeDistance);
                $distanceEntity->setWorkDistance($workDistance);
                $distanceEntity->setMaxDistance($maxDistanceValue);
                $distanceEntity->setCalculatedAt(new \DateTime());

                $this->distanceRepository->save($distanceEntity);
                $totalCalculations++;

                // Batch flush
                if ($totalCalculations % $batchSize === 0) {
                    $this->entityManager->flush();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Final flush
        $this->entityManager->flush();

        $io->success([
            "Processing completed!",
            "Total calculations: {$totalCalculations}",
            "New calculations: {$newCalculations}",
            "Skipped calculations: {$skippedCalculations}"
        ]);

        if ($showStats) {
            $this->displayStatistics($io);
        }

        return Command::SUCCESS;
    }

    private function displayStatistics(SymfonyStyle $io): void
    {
        $stats = $this->distanceRepository->getStatistics();
        
        $io->section("Distance Calculation Statistics");
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Calculations', $stats['total_calculations']],
                ['Unique Drivers', $stats['unique_drivers']],
                ['Unique Passengers', $stats['unique_passengers']],
                ['Average Home Distance (m)', $stats['average_home_distance']],
                ['Average Work Distance (m)', $stats['average_work_distance']],
                ['Average Max Distance (m)', $stats['average_max_distance']],
                ['Min Max Distance (m)', $stats['min_max_distance']],
                ['Max Max Distance (m)', $stats['max_max_distance']]
            ]
        );
    }

    private function calculateMinDistanceToRoute(float $lat, float $lng, array $routeCoordinates): float
    {
        $minDistance = PHP_FLOAT_MAX;

        foreach ($routeCoordinates as $routePoint) {
            $distance = $this->calculateDistance(
                $lat,
                $lng,
                $routePoint['lat'],
                $routePoint['lng']
            );
            
            if ($distance < $minDistance) {
                $minDistance = $distance;
            }
        }

        return $minDistance;
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

        return $earthRadius * $c;
    }

    private function outputTable(array $passengers, SymfonyStyle $io): void
    {
        if (empty($passengers)) {
            $io->note("No passengers found on the route.");
            return;
        }

        $headers = [
            'UID',
            'Name',
            'Phone',
            'Home Address',
            'Work Address',
            'Home Dist (m)',
            'Work Dist (m)',
            'Max Dist (m)',
            'Created'
        ];

        $rows = array_map(function($passenger) {
            return [
                $passenger['uid'],
                $passenger['name'],
                $passenger['phone'],
                $passenger['home_address'],
                $passenger['work_address'],
                $passenger['home_distance'],
                $passenger['work_distance'],
                $passenger['max_distance_to_route'],
                $passenger['created_at']
            ];
        }, $passengers);

        $io->table($headers, $rows);
    }

    private function outputJson(array $passengers, ?string $outputFile, SymfonyStyle $io): void
    {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_passengers' => count($passengers),
            'passengers' => $passengers
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);

        if ($outputFile) {
            file_put_contents($outputFile, $json);
            $io->success("Results saved to: {$outputFile}");
        } else {
            $io->writeln($json);
        }
    }

    private function outputCsv(array $passengers, ?string $outputFile, SymfonyStyle $io): void
    {
        if (empty($passengers)) {
            $io->note("No passengers found on the route.");
            return;
        }

        $headers = [
            'UID',
            'Name',
            'Phone',
            'Home Address',
            'Work Address',
            'Home Latitude',
            'Home Longitude',
            'Work Latitude',
            'Work Longitude',
            'Home Distance (m)',
            'Work Distance (m)',
            'Max Distance to Route (m)',
            'Created At'
        ];

        $csvData = [$headers];

        foreach ($passengers as $passenger) {
            $csvData[] = [
                $passenger['uid'],
                $passenger['name'],
                $passenger['phone'],
                $passenger['home_address'],
                $passenger['work_address'],
                $passenger['home_lat'],
                $passenger['home_lng'],
                $passenger['work_lat'],
                $passenger['work_lng'],
                $passenger['home_distance'],
                $passenger['work_distance'],
                $passenger['max_distance_to_route'],
                $passenger['created_at']
            ];
        }

        $csv = '';
        foreach ($csvData as $row) {
            $csv .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        if ($outputFile) {
            file_put_contents($outputFile, $csv);
            $io->success("Results saved to: {$outputFile}");
        } else {
            $io->writeln($csv);
        }
    }
} 