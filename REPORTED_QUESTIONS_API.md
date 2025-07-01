# Reported Questions API

This document describes the API endpoints for managing reported questions in the exam quiz backend.

## Overview

The Reported Questions API allows users to report questions that may have issues or need attention. Each reported question includes information about the subject, question text, topic, and sub-topic.

## Database Schema

The `reported_question` table has the following structure:

```sql
CREATE TABLE `reported_question` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) DEFAULT NULL,
  `question_text` text DEFAULT NULL,
  `question_topic` varchar(255) DEFAULT NULL,
  `sub_topic` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reported_question_subject_idx` (`subject_id`),
  CONSTRAINT `reported_question_subject_fk` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## API Endpoints

### Base URL
All endpoints are prefixed with `/api/reported-questions`

### 1. Create a New Reported Question

**Endpoint:** `POST /api/reported-questions`

**Description:** Creates a new reported question entry.

**Request Body:**
```json
{
    "subject_id": 1,                    // Required: ID of the subject
    "question_text": "Question content", // Required: The question text to report
    "question_topic": "Algebra",         // Optional: Main topic of the question
    "sub_topic": "Linear Equations"      // Optional: Sub-topic of the question
}
```

**Response (201 Created):**
```json
{
    "status": "OK",
    "message": "Reported question created successfully",
    "data": {
        "id": 1,
        "subject": "Mathematics",
        "question_text": "Question content",
        "question_topic": "Algebra",
        "sub_topic": "Linear Equations",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

**Error Responses:**

- **400 Bad Request** - Missing required fields:
```json
{
    "status": "NOK",
    "message": "Subject ID is required"
}
```

- **404 Not Found** - Invalid subject ID:
```json
{
    "status": "NOK",
    "message": "Subject not found"
}
```

### 2. Get All Reported Questions

**Endpoint:** `GET /api/reported-questions`

**Description:** Retrieves all reported questions.

**Response (200 OK):**
```json
{
    "status": "OK",
    "data": [
        {
            "id": 1,
            "subject": "Mathematics",
            "subject_id": 1,
            "question_text": "Question content",
            "question_topic": "Algebra",
            "sub_topic": "Linear Equations",
            "created_at": "2024-01-15 10:30:00",
            "updated_at": "2024-01-15 10:30:00"
        }
    ],
    "count": 1
}
```

### 3. Delete a Reported Question

**Endpoint:** `DELETE /api/reported-questions/{id}`

**Description:** Deletes a specific reported question by ID.

**Parameters:**
- `id` (path parameter): The ID of the reported question to delete

**Response (200 OK):**
```json
{
    "status": "OK",
    "message": "Reported question deleted successfully"
}
```

**Error Response:**

- **404 Not Found** - Question not found:
```json
{
    "status": "NOK",
    "message": "Reported question not found"
}
```

## Usage Examples

### Creating a Reported Question

```bash
curl -X POST http://localhost:8000/api/reported-questions \
  -H "Content-Type: application/json" \
  -d '{
    "subject_id": 1,
    "question_text": "This question has incorrect information",
    "question_topic": "Calculus",
    "sub_topic": "Derivatives"
  }'
```

### Getting All Reported Questions

```bash
curl -X GET http://localhost:8000/api/reported-questions
```

### Deleting a Reported Question

```bash
curl -X DELETE http://localhost:8000/api/reported-questions/1
```

## Testing

Run the tests for the ReportedQuestion API:

```bash
php bin/phpunit tests/Controller/ReportedQuestionControllerTest.php
```

## Database Setup

To create the required database table, run the SQL script:

```bash
mysql -u your_username -p your_database < create_reported_question_table.sql
```

## Entity Structure

The `ReportedQuestion` entity includes:

- **id**: Primary key (auto-increment)
- **subject**: Many-to-one relationship with Subject entity
- **questionText**: Text field for the reported question content
- **questionTopic**: String field for the main topic
- **subTopic**: String field for the sub-topic
- **createdAt**: Timestamp when the report was created
- **updatedAt**: Timestamp when the report was last updated

## Related Files

- **Entity**: `src/Entity/ReportedQuestion.php`
- **Repository**: `src/Repository/ReportedQuestionRepository.php`
- **Controller**: `src/Controller/ReportedQuestionController.php`
- **Tests**: `tests/Controller/ReportedQuestionControllerTest.php`
- **Database Schema**: `create_reported_question_table.sql` 