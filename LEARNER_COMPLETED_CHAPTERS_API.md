# Learner Completed Chapters API

## Overview
This API manages learner completed chapters, tracking which chapters learners have completed along with their performance metrics.

## Endpoints

### Add Completed Chapter
**POST** `/api/learner-completed-chapters`

Adds a new completed chapter record for a learner.

#### Request Body
```json
{
    "learnerUid": "string (required)",
    "chapterName": "string (required)", 
    "bookTitle": "string (required)",
    "duration": "integer (optional)",
    "score": "integer (optional)",
    "profileId": "integer (optional)"
}
```

#### Response (201 Created)
```json
{
    "id": 1,
    "learnerUid": "learner123",
    "profileId": 456,
    "chapterName": "Chapter 1: Introduction",
    "bookTitle": "Mathematics Fundamentals",
    "completedAt": "2024-12-20 10:30:00",
    "duration": 1800,
    "score": 85,
    "message": "Chapter completed successfully"
}
```

### Get Completed Chapters by Learner
**GET** `/api/learner-completed-chapters/learner/{learnerUid}`

Retrieves all completed chapters for a specific learner.

#### Response (200 OK)
```json
{
    "learnerUid": "learner123",
    "completedChapters": [
        {
            "id": 1,
            "learnerUid": "learner123",
            "profileId": 456,
            "chapterName": "Chapter 1: Introduction",
            "bookTitle": "Mathematics Fundamentals",
            "completedAt": "2024-12-20 10:30:00",
            "duration": 1800,
            "score": 85
        }
    ],
    "totalCount": 1
}
```

### Get Completed Chapters by Learner and Book
**GET** `/api/learner-completed-chapters/learner/{learnerUid}/book/{bookTitle}`

Retrieves completed chapters for a specific learner and book.

#### Response (200 OK)
```json
{
    "learnerUid": "learner123",
    "bookTitle": "Mathematics Fundamentals",
    "completedChapters": [
        {
            "id": 1,
            "learnerUid": "learner123",
            "profileId": 456,
            "chapterName": "Chapter 1: Introduction",
            "bookTitle": "Mathematics Fundamentals",
            "completedAt": "2024-12-20 10:30:00",
            "duration": 1800,
            "score": 85
        }
    ],
    "totalCount": 1
}
```

### Get Completed Chapters Count by Learner
**GET** `/api/learner-completed-chapters/learner/{learnerUid}/count`

Returns the total count of completed chapters for a learner.

#### Response (200 OK)
```json
{
    "learnerUid": "learner123",
    "totalCompletedChapters": 5
}
```

### Check Chapter Completion
**GET** `/api/learner-completed-chapters/learner/{learnerUid}/chapter/{chapterName}/check`

Checks if a specific chapter has been completed by a learner.

#### Response (200 OK)
```json
{
    "learnerUid": "learner123",
    "chapterName": "Chapter 1: Introduction",
    "isCompleted": true
}
```

## Database Schema

The `learner_completed_chapter` table includes the following fields:

- `id` (INT, Primary Key, Auto Increment)
- `learner_uid` (VARCHAR(45), Not Null)
- `profile_id` (INT, Nullable) - **NEW FIELD**
- `chapter_name` (VARCHAR(255), Not Null)
- `book_title` (VARCHAR(255), Not Null)
- `completed_at` (DATETIME, Default: CURRENT_TIMESTAMP)
- `duration` (INT, Nullable)
- `score` (INT, Nullable)

## Migration

To apply the database changes, run:
```bash
php bin/console doctrine:migrations:migrate
```

This will add the `profile_id` column to the existing `learner_completed_chapter` table.

## Features

- **Duplicate Prevention**: The system prevents duplicate entries for the same learner and chapter combination
- **Automatic Timestamp**: Completion time is automatically recorded when a chapter is marked as completed
- **Duration Tracking**: Optional duration tracking in seconds for time spent on chapters
- **Score Tracking**: Optional score tracking for chapter completion performance
- **Efficient Indexing**: Database indexes on learner_uid, chapter_name, and book_title for fast queries
- **Comprehensive API**: Full CRUD operations with additional utility endpoints

## Usage Examples

### Mark a chapter as completed
```bash
curl -X POST http://localhost:8000/api/learner-completed-chapters \
  -H "Content-Type: application/json" \
  -d '{
    "learnerUid": "learner-123",
    "chapterName": "Chapter 1: Introduction",
    "bookTitle": "The Adventure Begins",
    "duration": 1200,
    "score": 85
  }'
```

### Get all completed chapters for a learner
```bash
curl -X GET http://localhost:8000/api/learner-completed-chapters/learner/learner-123
```

### Check if a chapter is completed
```bash
curl -X GET "http://localhost:8000/api/learner-completed-chapters/learner/learner-123/chapter/Chapter 1: Introduction/check"
```

## Error Handling

The API returns appropriate HTTP status codes:
- `200` - Success
- `201` - Created (for new completed chapters)
- `400` - Bad Request (missing required fields)
- `500` - Internal Server Error

All error responses include a descriptive error message in the response body. 