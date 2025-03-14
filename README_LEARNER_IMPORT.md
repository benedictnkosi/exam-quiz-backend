# Learner Import Functionality

This document describes how to use the learner import functionality to import learners from JSON data.

## JSON Format

The JSON format for importing learners should be an array of learner objects with the following structure:

```json
[
  {
    "id": 28,
    "uid": "BbYmf2Ro8rWQbzW7idIxSZVSaL82",
    "grade": 3,
    "points": 0,
    "name": "Mluleki",
    "notification_hour": 1,
    "role": "admin",
    "created": "2025-02-09 11:01:59",
    "lastSeen": "2025-02-09 11:48:44",
    "school_address": null,
    "school_name": null,
    "school_latitude": 0,
    "school_longitude": 0,
    "terms": null,
    "curriculum": null,
    "private_school": false,
    "email": "NULL",
    "rating": 0,
    "rating_cancelled": "2025-02-02 11:01:59+00",
    "streak": 0,
    "streak_last_updated": "2025-03-10 17:24:19.892096+00",
    "avatar": "8.png"
  },
  // More learners...
]
```

## API Usage

You can import learners using the API endpoint:

```
POST /api/learners/import
```

### Using JSON Content

Send a POST request with the JSON content in the request body:

```bash
curl -X POST -H "Content-Type: application/json" -d @learners.json http://localhost:8000/api/learners/import
```

### Using File Upload

Send a POST request with the file in the `file` field:

```bash
curl -X POST -F "file=@learners.json" http://localhost:8000/api/learners/import
```

## Command Line Usage

You can also import learners using the command line:

```bash
php bin/console app:import-learners path/to/learners.json
```

## Response Format

The API will respond with a JSON object containing:

- `success`: Boolean indicating if the import was successful
- `message`: A message describing the result
- `count`: The number of successfully imported learners
- `errors`: An array of error messages (if any)

Example successful response:

```json
{
  "success": true,
  "message": "Successfully imported 3 learners",
  "count": 3
}
```

Example response with errors:

```json
{
  "success": true,
  "message": "Imported 2 learners with 1 errors",
  "count": 2,
  "errors": [
    "Error importing learner at index 2: Grade with ID 5 not found"
  ]
}
```

## Notes

- If a learner with the same `uid` or `id` already exists, it will be updated with the new data.
- The `grade` field should contain a valid grade ID that exists in the database.
- Date fields should be in the format `YYYY-MM-DD HH:MM:SS` or `YYYY-MM-DD HH:MM:SS+00`.
- If the `email` field contains the string "NULL", it will be set to null. 