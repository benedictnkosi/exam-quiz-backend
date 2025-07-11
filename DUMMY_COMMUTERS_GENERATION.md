# Dummy Commuters Generation

This document explains how to generate dummy data for commuters and commute distances for testing purposes.

## Overview

The dummy data generation creates realistic commuter profiles with:
- **Home locations** around coordinates: `-26.19326440, 28.09080250` (Johannesburg Central area)
- **Work locations** around coordinates: `-26.10542700, 28.04568390` (Sandton/Northern Johannesburg area)
- Realistic South African names, phone numbers, and addresses
- Optional commute distance calculations between drivers and passengers

## Command Usage

### Basic Usage

```bash
# Generate 50 commuters (30% drivers, 70% passengers)
php bin/console app:generate-dummy-commuters

# Generate 100 commuters with 40% drivers
php bin/console app:generate-dummy-commuters --count=100 --drivers-ratio=0.4

# Generate commuters with commute distances
php bin/console app:generate-dummy-commuters --with-distances

# Clear existing data and generate new data
php bin/console app:generate-dummy-commuters --clear-existing --with-distances

# Note: Route coordinates are automatically generated for all drivers using realistic dummy routes
```

### Using the Shell Script

For easier usage, you can use the provided shell script:

```bash
# Make script executable (first time only)
chmod +x scripts/generate_dummy_commuters.sh

# Basic usage
./scripts/generate_dummy_commuters.sh

# Generate 100 commuters with distances
./scripts/generate_dummy_commuters.sh -c 100 -w

# Clear existing data and generate 75 commuters (50% drivers) with distances
./scripts/generate_dummy_commuters.sh -c 75 -d 0.5 -w -x

# Show help
./scripts/generate_dummy_commuters.sh -h
```

## Command Options

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--count` | `-c` | Number of commuters to generate | 50 |
| `--drivers-ratio` | `-d` | Ratio of drivers (0.0-1.0) | 0.3 |
| `--with-distances` | `-w` | Generate commute distances | false |
| `--clear-existing` | `-x` | Clear existing data before generating | false |

## Generated Data

### Commuter Profiles

### Route Coordinates

For drivers, the system automatically generates route coordinates:
- **15 route points** between home and work locations
- **Realistic dummy routes** with road-like variations and curves
- **No external API dependencies** - works offline
- **Coordinates stored** in JSON format for easy API consumption

Each commuter includes:
- **Name**: Random South African first and last names
- **Phone Number**: South African format (+27XXXXXXXXX)
- **Home Address**: Random street address in Johannesburg area
- **Work Address**: Random street address in Sandton area
- **Coordinates**: Realistic GPS coordinates around specified areas
- **Type**: Either 'driver' or 'passenger'
- **Status**: 'active' (default)
- **Subscription**: 30% chance of having a subscription (premium/basic/pro)
- **Push Token**: 70% chance of having a notification token
- **Route Coordinates**: Generated for drivers (15 points along their route from home to work)

### Geographic Distribution

The generated commuters are distributed around these areas:

1. **Johannesburg Central** (Home area)
   - Coordinates: `-26.25` to `-26.15` lat, `28.05` to `28.15` lng
   - Cities: Johannesburg, Braamfontein, Hillbrow, Newtown, Marshalltown

2. **Sandton/Northern Johannesburg** (Work area)
   - Coordinates: `-26.15` to `-26.05` lat, `28.00` to `28.10` lng
   - Cities: Sandton, Rosebank, Melville, Parktown, Bryanston

3. **Midrand Area**
   - Coordinates: `-26.10` to `-26.00` lat, `27.95` to `28.05` lng
   - Cities: Midrand, Centurion, Randburg, Fourways, Dainfern

4. **Roodepoort Area**
   - Coordinates: `-26.20` to `-26.10` lat, `27.85` to `27.95` lng
   - Cities: Roodepoort, Northcliff, Fairland, Weltevreden Park, Constantia Kloof

### Commute Distances

When `--with-distances` is used, the system generates:
- Distance calculations between each driver and passenger
- Home-to-home distances
- Work-to-work distances
- Maximum distance (used for matching)
- Realistic calculation timestamps (within last 30 days)
- 70% chance of creating a distance record for each driver-passenger pair

## Examples

### Example 1: Basic Testing Data
```bash
# Generate 25 commuters for basic testing
php bin/console app:generate-dummy-commuters --count=25
```

### Example 2: Full Testing with Distances
```bash
# Generate 100 commuters with distances for comprehensive testing
php bin/console app:generate-dummy-commuters --count=100 --with-distances
```

### Example 3: Driver-Heavy Scenario
```bash
# Generate 50 commuters with 60% drivers for driver-heavy testing
php bin/console app:generate-dummy-commuters --count=50 --drivers-ratio=0.6 --with-distances
```

### Example 4: Fresh Start
```bash
# Clear all existing data and start fresh
php bin/console app:generate-dummy-commuters --clear-existing --count=75 --with-distances
```

## API Testing

After generating dummy data, you can test the API endpoints:

```bash
# List all commuters
curl -X GET "http://localhost:8000/api/commuters"

# Get a specific commuter
curl -X GET "http://localhost:8000/api/commuters/{uid}"

# Get commuter matches
curl -X GET "http://localhost:8000/api/commuters/{uid}/matches?max_distance=5000"

# Get commuter statistics
curl -X GET "http://localhost:8000/api/commuters/stats"
```

## Database Tables

The command generates data for these tables:
- `commuters` - Commuter profiles
- `commute_distances` - Distance calculations between drivers and passengers

## Notes

- All coordinates are generated within realistic ranges around the specified locations
- Phone numbers follow South African format (+27XXXXXXXXX)
- Names are common South African names
- Addresses use realistic street names
- Distances are calculated using the Haversine formula for accuracy
- All commuters are set to 'active' status by default
- The system prevents duplicate phone numbers and UIDs 