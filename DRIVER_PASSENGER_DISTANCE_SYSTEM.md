# Driver-Passenger Distance Tracking System

This system tracks distances between drivers and passengers to optimize ride-sharing matching and avoid recalculating the same distances repeatedly.

## Overview

The system consists of:
1. **DriverPassengerDistance Entity** - Stores calculated distances
2. **CalculateAllDriverPassengerDistancesCommand** - Main command for bulk processing
3. **Updated FindPassengersOnRouteCommand** - Enhanced with caching support

## Distance Calculation Logic

The system calculates distances considering both passenger pickup and dropoff locations:

1. **Home-to-Route Distance**: Distance from passenger's home to the closest point on driver's route
2. **Work-to-Route Distance**: Distance from passenger's work to the closest point on driver's route
3. **Final Distance**: Uses the maximum of home and work distances (both locations must be reasonably close)

This ensures that both pickup (home) and dropoff (work) locations are convenient for the driver's route.

## Database Schema

### commute_distances Table

```sql
CREATE TABLE commute_distances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_uid VARCHAR(255) NOT NULL,
    passenger_uid VARCHAR(255) NOT NULL,
    home_distance DECIMAL(10,2) NOT NULL,
    work_distance DECIMAL(10,2) NOT NULL,
    max_distance DECIMAL(10,2) NOT NULL,
    calculated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    
    -- Indexes for performance
    INDEX driver_passenger_idx (driver_uid, passenger_uid),
    INDEX home_distance_idx (home_distance),
    INDEX work_distance_idx (work_distance),
    INDEX max_distance_idx (max_distance),
    INDEX calculated_at_idx (calculated_at),
    
    -- Unique constraint to prevent duplicate calculations
    UNIQUE KEY unique_driver_passenger (driver_uid, passenger_uid)
);
```

## Commands

### 1. Calculate All Driver-Passenger Distances

**Command:** `app:calculate-all-driver-passenger-distances`

**Purpose:** Calculate distances between all drivers and passengers, storing results to avoid recalculation.

**Usage:**
```bash
# Basic usage - calculate all distances
php bin/console app:calculate-all-driver-passenger-distances

# With options
php bin/console app:calculate-all-driver-passenger-distances \
    --batch-size=200 \
    --max-distance=5000 \
    --show-stats \
    --force-recalculate
```

**Options:**
- `--force-recalculate`: Force recalculation of existing distances
- `--batch-size, -b`: Batch size for processing (default: 100)
- `--max-distance, -d`: Only save distances within this range in meters (0 = save all)
- `--show-stats, -s`: Show statistics after processing
- `--dry-run`: Show what would be calculated without saving
- `--cleanup-old`: Delete calculations older than X days

**Examples:**

```bash
# Calculate all distances with progress bar
php bin/console app:calculate-all-driver-passenger-distances

# Only save distances within 2km
php bin/console app:calculate-all-driver-passenger-distances --max-distance=2000

# Force recalculation of all distances
php bin/console app:calculate-all-driver-passenger-distances --force-recalculate

# Dry run to see what would be calculated
php bin/console app:calculate-all-driver-passenger-distances --dry-run

# Clean up old calculations and show stats
php bin/console app:calculate-all-driver-passenger-distances --cleanup-old=30 --show-stats
```

### 2. Find Passengers on Route (Enhanced)

**Command:** `app:find-passengers-on-route`

**Purpose:** Find passengers on a specific driver's route (now supports all drivers).

**Usage:**
```bash
# Find passengers for specific driver
php bin/console app:find-passengers-on-route driver-123

# Process all drivers
php bin/console app:find-passengers-on-route

# Process all drivers with options
php bin/console app:find-passengers-on-route \
    --max-distance=1000 \
    --force-recalculate \
    --batch-size=150 \
    --show-stats
```

## Repository Methods

The `DriverPassengerDistanceRepository` provides these methods:

### Query Methods
- `existsByDriverAndPassenger($driverUid, $passengerUid)`: Check if calculation exists
- `findByDriverAndPassenger($driverUid, $passengerUid)`: Get specific calculation
- `findByDriver($driverUid, $maxDistance)`: Get all distances for a driver
- `findByPassenger($passengerUid, $maxDistance)`: Get all distances for a passenger

### Statistics Methods
- `getStatistics()`: Get overall statistics
- `getCalculatedDriverUids()`: Get all driver UIDs with calculations
- `getCalculatedPassengerUids()`: Get all passenger UIDs with calculations

### Maintenance Methods
- `deleteOldCalculations($daysOld)`: Delete old calculations

## Performance Features

### 1. Caching
- Distances are calculated once and stored
- Subsequent runs skip already calculated distances
- Use `--force-recalculate` to update existing calculations

### 2. Batch Processing
- Configurable batch size for database operations
- Reduces memory usage and improves performance
- Default batch size: 100

### 3. Distance Filtering
- Only save distances within specified range
- Reduces database size and improves query performance
- Use `--max-distance` to set threshold

### 4. Database Indexes
- Optimized indexes for common queries
- Composite index on driver_uid + passenger_uid
- Separate indexes for distance and calculation date

## Usage Scenarios

### 1. Initial Setup
```bash
# First run - calculate all distances
php bin/console app:calculate-all-driver-passenger-distances --show-stats
```

### 2. Regular Updates
```bash
# Daily update - only calculate new combinations
php bin/console app:calculate-all-driver-passenger-distances --batch-size=500
```

### 3. Data Cleanup
```bash
# Clean up old calculations (older than 30 days)
php bin/console app:calculate-all-driver-passenger-distances --cleanup-old=30
```

### 4. Testing
```bash
# Dry run to see what would be calculated
php bin/console app:calculate-all-driver-passenger-distances --dry-run --show-stats
```

## Integration with API

You can use the repository methods in your controllers:

```php
// In a controller
public function findNearbyPassengers(string $driverUid, float $maxDistance = 1000): JsonResponse
{
    $distances = $this->distanceRepository->findByDriver($driverUid, $maxDistance);
    
    $passengers = [];
    foreach ($distances as $distance) {
        $passenger = $this->commuterRepository->findOneBy(['uid' => $distance->getPassengerUid()]);
        if ($passenger) {
            $passengers[] = [
                'uid' => $passenger->getUid(),
                'name' => $passenger->getName(),
                'distance' => $distance->getDistance(),
                'calculated_at' => $distance->getCalculatedAt()->format('Y-m-d H:i:s')
            ];
        }
    }
    
    return $this->json(['passengers' => $passengers]);
}
```

## Monitoring and Maintenance

### Statistics
```bash
# Get current statistics
php bin/console app:calculate-all-driver-passenger-distances --show-stats
```

### Database Size
Monitor the `commute_distances` table size:
```sql
SELECT 
    COUNT(*) as total_calculations,
    COUNT(DISTINCT driver_uid) as unique_drivers,
    COUNT(DISTINCT passenger_uid) as unique_passengers,
    AVG(home_distance) as avg_home_distance,
    AVG(work_distance) as avg_work_distance,
    AVG(max_distance) as avg_max_distance,
    MIN(max_distance) as min_max_distance,
    MAX(max_distance) as max_max_distance
FROM commute_distances;
```

### Cleanup Strategy
- Set up a cron job to clean old calculations weekly
- Monitor table growth and adjust cleanup frequency
- Consider archiving old data instead of deleting

## Error Handling

The system handles various edge cases:
- Drivers without route coordinates are skipped
- Passengers without coordinates are skipped
- Duplicate calculations are prevented
- Database errors are logged and reported

## Best Practices

1. **Run regularly**: Set up a cron job to run the calculation command daily
2. **Monitor performance**: Watch execution time and adjust batch sizes
3. **Clean up old data**: Regularly delete old calculations to maintain performance
4. **Use dry runs**: Test changes with `--dry-run` before applying
5. **Backup before cleanup**: Always backup before running cleanup operations 