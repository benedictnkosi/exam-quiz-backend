# Address Lookup API

This service provides address lookup functionality using the Google Places API. It allows users to search for addresses by providing the first part of an address (minimum 5 characters).

## Setup

### 1. Environment Configuration

Add the Google Places API key to your environment variables:

```bash
# .env file
GOOGLE_PLACES_API_KEY=your_google_places_api_key_here
```

### 2. Google Places API Key

To get a Google Places API key:

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the "Places API" and "Maps JavaScript API"
4. Create credentials (API Key)
5. Restrict the API key to your domain for security

## API Endpoints

### 1. Address Lookup

**Endpoint:** `GET /api/address/lookup`

**Parameters:**
- `q` (required): Address prefix (minimum 5 characters)
- `country` (optional): Country code to restrict results (e.g., 'ZA' for South Africa)
- `types` (optional): Comma-separated list of place types to filter results

**Response Fields:**
- `place_id`: Google Places unique identifier
- `description`: Full formatted address
- `main_text`: Primary address component
- `secondary_text`: Secondary address component (city, country, etc.)
- `types`: Array of place types
- `matched_substrings`: Information about matched text
- `lat`: Latitude coordinate (if available)
- `lng`: Longitude coordinate (if available)

**Example Request:**
```
GET /api/address/lookup?q=123 Ma&country=ZA
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "query": "123 Ma",
        "country_code": "ZA",
        "suggestions": [
            {
                "place_id": "ChIJ...",
                "description": "123 Main Street, Johannesburg, South Africa",
                "main_text": "123 Main Street",
                "secondary_text": "Johannesburg, South Africa",
                "types": ["street_address"],
                "matched_substrings": [
                    {
                        "length": 5,
                        "offset": 0
                    }
                ],
                "lat": -26.2041,
                "lng": 28.0473
            }
        ],
        "count": 1
    }
}
```

### 2. Place Details

**Endpoint:** `GET /api/address/details/{placeId}`

**Parameters:**
- `placeId` (required): Google Places place_id from the lookup response

**Example Request:**
```
GET /api/address/details/ChIJ...
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "place_id": "ChIJ...",
        "formatted_address": "123 Main Street, Johannesburg, 2000, South Africa",
        "geometry": {
            "location": {
                "lat": -26.2041,
                "lng": 28.0473
            }
        },
        "name": "123 Main Street",
        "types": ["street_address"],
        "address_components": [...]
    }
}
```

### 3. Health Check

**Endpoint:** `GET /api/address/health`

**Example Response:**
```json
{
    "status": "healthy",
    "service": "Address Lookup API",
    "timestamp": "2024-01-15 10:30:00"
}
```

## Error Responses

### Invalid Input (400)
```json
{
    "error": "Invalid input",
    "message": "Address prefix must be at least 5 characters long"
}
```

### Missing Query Parameter (400)
```json
{
    "error": "Address prefix is required",
    "message": "Please provide a query parameter \"q\" with at least 5 characters"
}
```

### Place Not Found (404)
```json
{
    "error": "Place not found",
    "message": "No details found for the provided place_id"
}
```

### Internal Server Error (500)
```json
{
    "error": "Internal server error",
    "message": "An error occurred while looking up addresses"
}
```

## Usage Examples

### Frontend JavaScript Example

```javascript
// Address lookup
async function lookupAddress(query, countryCode = null) {
    const params = new URLSearchParams({ q: query });
    if (countryCode) {
        params.append('country', countryCode);
    }
    
    const response = await fetch(`/api/address/lookup?${params}`);
    const data = await response.json();
    
    if (data.success) {
        return data.data.suggestions;
    } else {
        throw new Error(data.message);
    }
}

// Get place details
async function getPlaceDetails(placeId) {
    const response = await fetch(`/api/address/details/${placeId}`);
    const data = await response.json();
    
    if (data.success) {
        return data.data;
    } else {
        throw new Error(data.message);
    }
}

// Usage
lookupAddress('123 Ma', 'ZA')
    .then(suggestions => {
        console.log('Address suggestions:', suggestions);
    })
    .catch(error => {
        console.error('Error:', error);
    });
```

### PHP Service Usage

```php
use App\Service\GooglePlacesService;

// In your controller or service
public function someMethod(GooglePlacesService $placesService)
{
    // Look up addresses
    $suggestions = $placesService->lookupAddress('123 Ma', 'ZA');
    
    // Get place details
    $details = $placesService->getPlaceDetails('ChIJ...');
}
```

## Configuration

The service is automatically configured in `config/services.yaml`:

```yaml
parameters:
    google_places_api_key: "%env(GOOGLE_PLACES_API_KEY)%"

services:
    App\Service\GooglePlacesService:
        arguments:
            $googlePlacesApiKey: "%google_places_api_key%"
```

## Testing

Run the tests to verify the service works correctly:

```bash
php bin/phpunit tests/Service/GooglePlacesServiceTest.php
```

## Security Considerations

1. **API Key Protection**: Always restrict your Google Places API key to your domain
2. **Rate Limiting**: Consider implementing rate limiting for the API endpoints
3. **Input Validation**: The service validates input length (minimum 5 characters)
4. **Error Logging**: All errors are logged for monitoring and debugging

## Dependencies

- Symfony HTTP Client (already included in the project)
- PSR Logger (already included in the project)
- Google Places API key (external service)

## Notes

- The service requires a minimum of 5 characters for address lookup
- Results are restricted to addresses by default
- Country filtering is supported for more targeted results
- All API calls are logged for monitoring purposes
- The service includes comprehensive error handling and logging 