# Commuter Matches API

This API endpoint retrieves all opposite type commuters for a given UID, filtered by distance criteria.

## Endpoint

```
GET /api/commuters/{uid}/matches
```

## Description

Returns all commuters of the opposite type (drivers for passengers, passengers for drivers) that have both home and work distances less than the specified threshold from the given commuter's route.

## Parameters

### Path Parameters
- `uid` (string, required): The UID of the commuter to find matches for

### Query Parameters
- `max_distance` (float, optional): Maximum distance threshold in meters (default: 5000)

## Response Format

### Success Response (200)

```json
{
    "success": true,
    "data": {
        "commuter": {
            "uid": "driver-123",
            "name": "John Driver",
            "type": "driver",
            "home": {
                "street": "123 Main St",
                "city": "Johannesburg",
                "province": "Gauteng",
                "full_address": "123 Main St, Johannesburg, Gauteng",
                "lat": -26.2041,
                "lng": 28.0473
            },
            "work": {
                "street": "456 Work Ave",
                "city": "Johannesburg",
                "province": "Gauteng",
                "full_address": "456 Work Ave, Johannesburg, Gauteng",
                "lat": -26.2141,
                "lng": 28.0573
            }
        },
        "opposite_type": "passenger",
        "max_distance_threshold": 5000,
        "matches": [
            {
                "uid": "passenger-456",
                "name": "Jane Passenger",
                "phone_number": "+1234567890",
                "type": "passenger",
                "home": {
                    "street": "123 Main St",
                    "city": "Johannesburg",
                    "province": "Gauteng",
                    "full_address": "123 Main St, Johannesburg, Gauteng",
                    "lat": -26.2041,
                    "lng": 28.0473
                },
                "work": {
                    "street": "456 Work Ave",
                    "city": "Johannesburg",
                    "province": "Gauteng",
                    "full_address": "456 Work Ave, Johannesburg, Gauteng",
                    "lat": -26.2141,
                    "lng": 28.0573
                },
                "distances": {
                    "home_distance": 150.25,
                    "work_distance": 200.50,
                    "max_distance": 200.50
                },
                "calculated_at": "2024-01-15 10:30:00"
            }
        ],
        "count": 1
    }
}
```

### Error Responses

#### 404 - Commuter Not Found
```json
{
    "error": "Commuter not found",
    "message": "No commuter found with the provided UID"
}
```

#### 500 - Internal Server Error
```json
{
    "error": "Internal server error",
    "message": "An error occurred while retrieving matches"
}
```

## Distance Filtering Logic

The API applies the following distance criteria:

1. **Home Distance**: Distance from the opposite commuter's home to the requesting commuter's route
2. **Work Distance**: Distance from the opposite commuter's work to the requesting commuter's route
3. **Max Distance**: The maximum of home and work distances (both locations must be within threshold)

Only commuters where **both** home and work distances are less than the specified threshold are returned.

## Examples

### Get matches for a driver (default 5000m threshold)
```bash
GET /api/commuters/driver-123/matches
```

### Get matches for a passenger with custom threshold
```bash
GET /api/commuters/passenger-456/matches?max_distance=2000
```

### Get matches for a driver with 1km threshold
```bash
GET /api/commuters/driver-789/matches?max_distance=1000
```

## Usage Scenarios

### 1. Driver Looking for Passengers
- Driver calls the API to find nearby passengers
- Returns passengers where both home and work are close to driver's route
- Results sorted by distance (closest first)

### 2. Passenger Looking for Drivers
- Passenger calls the API to find nearby drivers
- Returns drivers where passenger's home and work are close to driver's route
- Results sorted by distance (closest first)

### 3. Ride-Sharing Matching
- Use this endpoint to find potential ride matches
- Filter by distance to ensure convenient pickup and dropoff
- Sort by distance to prioritize closest matches

## Requirements

- The requesting commuter must exist in the system
- Distance calculations must have been performed (run the distance calculation commands first)
- Both home and work coordinates must be available for all commuters

## Performance Notes

- Results are sorted by max distance (closest first)
- Distance calculations are cached in the database
- Query uses indexes on distance fields for optimal performance
- Consider pagination for large result sets in future versions

## Related Commands

Before using this API, ensure distance calculations are up to date:

```bash
# Calculate all driver-passenger distances
php bin/console app:calculate-all-driver-passenger-distances

# Or calculate for specific driver
php bin/console app:find-passengers-on-route driver-123
``` 