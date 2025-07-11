# Find Passengers on Route Command

This command finds passengers that are within a specified distance from a driver's route. It calculates the minimum distance between each passenger's home location and any point on the driver's route.

## Command Name
```
app:find-passengers-on-route
```

## Usage

### Basic Usage
```bash
php bin/console app:find-passengers-on-route <driver-uid>
```

### With Options
```bash
php bin/console app:find-passengers-on-route <driver-uid> [--max-distance=1000] [--output-format=table] [--output-file=results.json]
```

## Arguments

- `driver-uid` (required): The UID of the driver whose route to check

## Options

- `--max-distance, -d`: Maximum distance in meters from route (default: 1000)
- `--output-format, -f`: Output format: json, table, or csv (default: table)
- `--output-file, -o`: Output file path (for json/csv formats)

## Examples

### Find passengers within 500 meters of driver route
```bash
php bin/console app:find-passengers-on-route driver-123 --max-distance=500
```

### Export results to JSON file
```bash
php bin/console app:find-passengers-on-route driver-123 --output-format=json --output-file=passengers.json
```

### Export results to CSV file
```bash
php bin/console app:find-passengers-on-route driver-123 --output-format=csv --output-file=passengers.csv
```

### Display results in table format (default)
```bash
php bin/console app:find-passengers-on-route driver-123 --output-format=table
```

## Output Formats

### Table Format (Default)
Displays results in a formatted table with columns:
- UID
- Name
- Phone
- Home Address
- Distance (m)
- Created

### JSON Format
```json
{
    "timestamp": "2024-01-15 10:30:00",
    "total_passengers": 3,
    "passengers": [
        {
            "uid": "passenger-1",
            "name": "John Doe",
            "phone": "+1234567890",
            "home_address": "123 Main St",
            "home_lat": -26.2041,
            "home_lng": 28.0473,
            "distance_to_route": 150.25,
            "created_at": "2024-01-10 09:00:00"
        }
    ]
}
```

### CSV Format
Exports data with headers:
- UID
- Name
- Phone
- Home Address
- Home Latitude
- Home Longitude
- Distance to Route (m)
- Created At

## Algorithm

The command uses the Haversine formula to calculate distances between geographic coordinates:

1. For each passenger, it calculates the distance from their home location to every point on the driver's route
2. It finds the minimum distance (closest point on the route)
3. If the minimum distance is within the specified maximum distance, the passenger is included in results
4. Results are sorted by distance (closest first)

## Requirements

- Driver must exist and have type 'driver'
- Driver must have route coordinates saved
- Passengers must have valid home latitude and longitude coordinates

## Error Handling

- Returns error if driver not found or is not a driver
- Returns error if driver has no route coordinates
- Skips passengers without valid coordinates
- Provides informative error messages

## Testing

Run the command tests:
```bash
php bin/phpunit tests/Command/FindPassengersOnRouteCommandTest.php
```

## Integration

This command can be integrated with:
- Cron jobs for regular passenger matching
- API endpoints for real-time passenger finding
- Notification systems to alert drivers of nearby passengers
- Ride-sharing applications

## Performance Considerations

- For large numbers of passengers, consider using database spatial queries
- The command loads all passengers into memory - for very large datasets, consider pagination
- Distance calculations use the Haversine formula which is accurate but computationally intensive 