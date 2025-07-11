<?php

namespace App\Command;

use App\Entity\DriverPassengerDistance;
use App\Repository\CommuterRepository;
use App\Repository\DriverPassengerDistanceRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calculate-all-driver-passenger-distances',
    description: 'Calculate distances between all drivers and passengers, skipping already calculated ones',
)]
class CalculateAllDriverPassengerDistancesCommand extends Command
{
    public function __construct(
        private CommuterRepository $commuterRepository,
        private DriverPassengerDistanceRepository $distanceRepository,
        private EntityManagerInterface $entityManager,
        private PushNotificationService $pushNotificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force-recalculate', null, InputOption::VALUE_NONE, 'Force recalculation of existing distances')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for processing (default: 100)', 100)
            ->addOption('max-distance', 'd', InputOption::VALUE_OPTIONAL, 'Only save distances within this range in meters (0 = save all)', 0)
            ->addOption('show-stats', 's', InputOption::VALUE_NONE, 'Show statistics after processing')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be calculated without saving')
            ->addOption('cleanup-old', null, InputOption::VALUE_OPTIONAL, 'Delete calculations older than X days', 0)
            ->setHelp('This command calculates distances between all drivers and passengers, storing results to avoid recalculation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $forceRecalculate = $input->getOption('force-recalculate');
        $batchSize = (int) $input->getOption('batch-size');
        $maxDistance = (int) $input->getOption('max-distance');
        $showStats = $input->getOption('show-stats');
        $dryRun = $input->getOption('dry-run');
        $cleanupDays = (int) $input->getOption('cleanup-old');

        $io->title("Calculate All Driver-Passenger Distances");

        // Cleanup old calculations if requested
        if ($cleanupDays > 0) {
            $deletedCount = $this->distanceRepository->deleteOldCalculations($cleanupDays);
            $io->info("Deleted {$deletedCount} old calculations (older than {$cleanupDays} days)");
        }

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
        $updatedCalculations = 0;
        $savedCalculations = 0;

        $progressBar = $io->createProgressBar(count($driversWithRoutes));
        $progressBar->start();

        foreach ($driversWithRoutes as $driver) {
            $driverUid = $driver->getUid();
            $routeCoordinates = $driver->getRouteCoordinates();

            foreach ($passengersWithCoords as $passenger) {
                $passengerId = $passenger->getId();

                // Check if calculation already exists
                if (!$forceRecalculate && $this->distanceRepository->existsByDriverAndPassenger($driver->getId(), $passengerId)) {
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

                // Skip if max distance is beyond threshold
                if ($maxDistance > 0 && $maxDistanceValue > $maxDistance) {
                    $skippedCalculations++;
                    continue;
                }

                // Send push notifications if home distance is less than 3km (3000 meters)
                if ($homeDistance < 3000) {
                    $this->sendMatchNotifications($driver, $passenger, $homeDistance, $io);
                }

                if (!$dryRun) {
                    // Save or update the distance calculation
                    $distanceEntity = $this->distanceRepository->findByDriverAndPassenger($driver->getId(), $passengerId);
                    
                    if (!$distanceEntity) {
                        $distanceEntity = new DriverPassengerDistance();
                        $distanceEntity->setDriverCommuteId($driver->getId());
                        $distanceEntity->setPassengerCommuteId($passenger->getId());
                        $newCalculations++;
                    } else {
                        $updatedCalculations++;
                    }

                    $distanceEntity->setHomeDistance($homeDistance);
                    $distanceEntity->setWorkDistance($workDistance);
                    $distanceEntity->setMaxDistance($maxDistanceValue);
                    $distanceEntity->setCalculatedAt(new \DateTime());

                    $this->distanceRepository->save($distanceEntity);
                    $savedCalculations++;
                }

                $totalCalculations++;

                // Batch flush
                if ($totalCalculations % $batchSize === 0 && !$dryRun) {
                    $this->entityManager->flush();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Final flush
        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success([
            "Processing completed!",
            "Total calculations: {$totalCalculations}",
            "New calculations: {$newCalculations}",
            "Updated calculations: {$updatedCalculations}",
            "Skipped calculations: {$skippedCalculations}",
            "Saved calculations: {$savedCalculations}"
        ]);

        if ($dryRun) {
            $io->note("This was a dry run - no data was saved");
        }

        if ($showStats) {
            $this->displayStatistics($io);
        }

        return Command::SUCCESS;
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

    private function sendMatchNotifications($driver, $passenger, float $homeDistance, SymfonyStyle $io): void
    {
        $distanceKm = round($homeDistance / 1000, 1);
        
        // Send notification to driver
        if ($driver->getPushNotificationToken()) {
            $driverNotification = [
                'to' => $driver->getPushNotificationToken(),
                'title' => '🚗 New Passenger Match!',
                'body' => "A passenger is available near your route! Distance: {$distanceKm}km from their home.",
                'data' => [
                    'type' => 'commuter_match',
                    'match_type' => 'passenger_found',
                    'passenger_uid' => $passenger->getUid(),
                    'passenger_name' => $passenger->getName(),
                    'distance_km' => $distanceKm,
                    'distance_meters' => round($homeDistance)
                ]
            ];

            $result = $this->pushNotificationService->sendPushNotification($driverNotification);
            if ($result['status'] === 'OK') {
                $io->text("✅ Sent match notification to driver: {$driver->getName()}");
            } else {
                $io->text("❌ Failed to send notification to driver: {$driver->getName()} - {$result['message']}");
            }
        }

        // Send notification to passenger
        if ($passenger->getPushNotificationToken()) {
            $passengerNotification = [
                'to' => $passenger->getPushNotificationToken(),
                'title' => '🚗 Driver Available!',
                'body' => "A driver is available near your home! Distance: {$distanceKm}km from your location.",
                'data' => [
                    'type' => 'commuter_match',
                    'match_type' => 'driver_found',
                    'driver_uid' => $driver->getUid(),
                    'driver_name' => $driver->getName(),
                    'distance_km' => $distanceKm,
                    'distance_meters' => round($homeDistance)
                ]
            ];

            $result = $this->pushNotificationService->sendPushNotification($passengerNotification);
            if ($result['status'] === 'OK') {
                $io->text("✅ Sent match notification to passenger: {$passenger->getName()}");
            } else {
                $io->text("❌ Failed to send notification to passenger: {$passenger->getName()} - {$result['message']}");
            }
        }
    }
} 