# Learner Completed Chapters API

This API allows tracking and retrieving learner completed chapters.

## Database Schema

The `learner_completed_chapter` table has the following structure:

```sql
CREATE TABLE `learner_completed_chapter` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `learner_uid` VARCHAR(45) NOT NULL,
    `chapter_name` VARCHAR(255) NOT NULL,
    `book_title` VARCHAR(255) NOT NULL,
    `completed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `duration` INT NULL,
    `score` INT NULL,
    INDEX `learner_completed_chapter_learner_uid_idx` (`learner_uid`),
    INDEX `learner_completed_chapter_chapter_name_idx` (`chapter_name`),
    INDEX `learner_completed_chapter_book_title_idx` (`book_title`),
    UNIQUE KEY `unique_learner_chapter` (`learner_uid`, `chapter_name`)
);
```

## API Endpoints

### 1. Add Completed Chapter

**POST** `/api/learner-completed-chapters`

Adds a completed chapter for a learner.

#### Request Body
```json
{
    "learnerUid": "learner-123",
    "chapterName": "Chapter 1: Introduction",
    "bookTitle": "The Adventure Begins",
    "duration": 1200,
    "score": 85
}
```

#### Response (201 Created)
```json
{
    "id": 1,
    "learnerUid": "learner-123",
    "chapterName": "Chapter 1: Introduction",
    "bookTitle": "The Adventure Begins",
    "completedAt": "2024-01-15 10:30:00",
    "duration": 1200,
    "score": 85,
    "message": "Chapter completed successfully"
}
```

#### Error Response (400 Bad Request)
```json
{
    "error": "Learner UID is required"
}
```

### 2. Get Completed Chapters by Learner

**GET** `/api/learner-completed-chapters/learner/{learnerUid}`

Retrieves all completed chapters for a specific learner.

#### Response (200 OK)
```json
{
    "learnerUid": "learner-123",
    "completedChapters": [
        {
            "id": 1,
            "learnerUid": "learner-123",
            "chapterName": "Chapter 1: Introduction",
            "bookTitle": "The Adventure Begins",
            "completedAt": "2024-01-15 10:30:00",
            "duration": 1200,
            "score": 85
        },
        {
            "id": 2,
            "learnerUid": "learner-123",
            "chapterName": "Chapter 2: The Journey",
            "bookTitle": "The Adventure Begins",
            "completedAt": "2024-01-16 14:20:00",
            "duration": 1500,
            "score": 92
        }
    ],
    "totalCount": 2
}
```

### 3. Get Completed Chapters by Learner and Book

**GET** `/api/learner-completed-chapters/learner/{learnerUid}/book/{bookTitle}`

Retrieves completed chapters for a specific learner and book.

#### Response (200 OK)
```json
{
    "learnerUid": "learner-123",
    "bookTitle": "The Adventure Begins",
    "completedChapters": [
        {
            "id": 1,
            "learnerUid": "learner-123",
            "chapterName": "Chapter 1: Introduction",
            "bookTitle": "The Adventure Begins",
            "completedAt": "2024-01-15 10:30:00",
            "duration": 1200,
            "score": 85
        }
    ],
    "totalCount": 1
}
```

### 4. Get Completed Chapters Count by Learner

**GET** `/api/learner-completed-chapters/learner/{learnerUid}/count`

Retrieves the total count of completed chapters for a specific learner.

#### Response (200 OK)
```json
{
    "learnerUid": "learner-123",
    "totalCompletedChapters": 5
}
```

### 5. Check Chapter Completion Status

**GET** `/api/learner-completed-chapters/learner/{learnerUid}/chapter/{chapterName}/check`

Checks if a specific chapter has been completed by a learner.

#### Response (200 OK)
```json
{
    "learnerUid": "learner-123",
    "chapterName": "Chapter 1: Introduction",
    "isCompleted": true
}
```

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