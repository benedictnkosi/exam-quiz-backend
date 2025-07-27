# Politician Connections API

## Overview

The Politician Connections API analyzes scandal data to identify connections between politicians based on their involvement in the same corruption scandals. This endpoint provides insights into the network of political relationships through shared scandal involvement.

## Endpoint

### GET `/api/politicians/connections`

Retrieves connections between politicians based on their involvement in the same scandals.

**Query Parameters:**
- `politician_id` (optional): Filter connections to only show those involving this specific politician by their ID. If not provided, returns all connections across all scandals.

**Request Examples:**
```bash
# Get all politician connections
curl "http://localhost:3000/api/politicians/connections"

# Get connections involving a specific politician by ID
curl "http://localhost:3000/api/politicians/connections?politician_id=123"
```

**Response Structure:**
```json
{
  "politician_id_filter": 123,
  "politician_name_filter": "Jacob Zuma",
  "connections": [
    {
      "politician1": "Jacob Zuma",
      "politician2": "Cyril Ramaphosa",
      "scandals": [
        {
          "title": "State Capture Scandal",
          "year": "2018",
          "description": "Corruption allegations involving state-owned enterprises",
          "main_politician": "Jacob Zuma",
          "involved_person": "Cyril Ramaphosa",
          "role": "Successor and investigator",
          "position": "Deputy President",
          "scandal_id": 1,
          "country": "South Africa"
        }
      ],
      "connection_strength": 1
    }
  ],
  "total_connections": 1,
  "total_scandals_analyzed": 5,
  "message": "Connections found involving Jacob Zuma"
}
```

## Response Fields

### Top Level
- `politician_id_filter`: The politician ID filter applied (null if no filter)
- `politician_name_filter`: The politician name corresponding to the ID filter (null if no filter or politician not found)
- `connections`: Array of politician connections found
- `total_connections`: Total number of unique connections found
- `total_scandals_analyzed`: Number of scandals that were analyzed
- `message`: Descriptive message about the results

### Connection Object
- `politician1`: First politician in the connection (alphabetically sorted)
- `politician2`: Second politician in the connection (alphabetically sorted)
- `scandals`: Array of scandals that connect these politicians
- `connection_strength`: Number of shared scandals between the politicians

### Scandal Object (within connections)
- `title`: Title of the scandal
- `year`: Year the scandal occurred
- `description`: Brief description of the scandal
- `main_politician`: The primary politician associated with the scandal
- `involved_person`: The other politician involved in this specific scandal
- `role`: The role of the involved person in the scandal
- `position`: The political position of the involved person at the time
- `scandal_id`: Database ID of the scandal record
- `country`: Country where the scandal occurred

## How It Works

1. **Scandal Analysis**: The API retrieves all scandals from the database (no longer limited by country)
2. **Politician ID Resolution**: If a politician ID filter is provided, the API finds the politician by ID to get their name
3. **Politician Filtering**: If a politician filter is provided, only scandals involving that politician are analyzed
4. **Involved Persons Extraction**: For each relevant scandal, it extracts the list of involved persons from the `involved_persons` field
5. **Connection Mapping**: Creates connections between the main politician and each involved person
6. **Deduplication**: Ensures each connection between two politicians is unique (alphabetically sorted names)
7. **Strength Calculation**: Counts the number of shared scandals between each pair of politicians
8. **Sorting**: Returns connections sorted by connection strength (highest first)

## Error Responses

### 500 - Server Error
```json
{
  "error": "Failed to retrieve politician connections",
  "details": "Error message details"
}
```

### Politician Not Found
If a politician ID is provided but not found, the API returns:
```json
{
  "politician_id_filter": 999,
  "politician_name_filter": null,
  "connections": [],
  "total_connections": 0,
  "message": "Politician not found with ID: 999"
}
```

## Use Cases

1. **Network Analysis**: Identify clusters of politicians involved in similar scandals
2. **Investigation Support**: Find potential witnesses or co-conspirators in corruption cases
3. **Political Research**: Understand the scope and reach of corruption networks
4. **Journalistic Research**: Discover hidden connections between political figures

## Example Scenarios

### Scenario 1: Multiple Shared Scandals
If two politicians are involved in multiple scandals together, their connection strength will be higher, indicating a stronger relationship.

### Scenario 2: No Connections
If no scandals have involved persons data, or if politicians are not connected through scandals, an empty connections array will be returned.

### Scenario 3: Single Scandal Connection
Politicians connected through only one scandal will have a connection strength of 1.

## Data Requirements

For connections to be found, scandals must have:
- `involved_persons` array with full names
- Valid `full_name`, `role`, and `position` fields for each involved person
- The involved person must be different from the main politician

## Performance Notes

- The API analyzes all scandals for the country in a single request
- Results are sorted by connection strength for optimal relevance
- Duplicate connections are automatically filtered out
- The response includes metadata about the analysis scope 