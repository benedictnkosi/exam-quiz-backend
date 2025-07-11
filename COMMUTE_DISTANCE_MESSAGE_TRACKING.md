# Commute Distance Message Tracking System

This system tracks when messages are sent between drivers and passengers for specific commute distances.

## Overview

The message tracking system consists of:
- `CommuteDistanceMessages` entity to store message tracking data
- `CommuteDistanceMessageTrackingService` to handle business logic
- API endpoints to manage and query message tracking data

## Database Schema

### commute_distance_messages table

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| commute_distance_id | INT | Foreign key to commute_distances table |
| passenger_last_message_date | DATETIME | Last message date from passenger (nullable) |
| driver_last_message_date | DATETIME | Last message date from driver (nullable) |
| created_at | DATETIME | Record creation timestamp |
| updated_at | DATETIME | Record last update timestamp |

## API Endpoints

### 1. Send Notification with Message Tracking

**POST** `/api/commuters/{uid}/send-notification`

Send a notification and optionally track the message for a commute distance.

**Request Body:**
```json
{
    "message": "Hello, are you available for a ride?",
    "title": "New Message",
    "commute_distance_id": 123,
    "sender_type": "passenger"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Notification sent successfully",
    "data": {
        "uid": "user123",
        "name": "John Doe",
        "title": "New Message",
        "message": "Hello, are you available for a ride?",
        "push_token": "ExponentPushToken[...]",
        "commute_distance_id": 123,
        "sender_type": "passenger"
    }
}
```

### 2. Get Message Tracking for a Commute Distance

**GET** `/api/commute-distances/{id}/message-tracking`

Get message tracking data for a specific commute distance.

**Response:**
```json
{
    "success": true,
    "data": {
        "commute_distance_id": 123,
        "message_tracking": {
            "id": 1,
            "commute_distance_id": 123,
            "passenger_last_message_date": "2024-12-24 10:30:00",
            "driver_last_message_date": "2024-12-24 11:15:00",
            "created_at": "2024-12-24 10:00:00",
            "updated_at": "2024-12-24 11:15:00"
        }
    }
}
```

### 3. Update Message Tracking

**PATCH** `/api/commute-distances/{id}/message-tracking`

Manually update message tracking for a commute distance.

**Request Body:**
```json
{
    "sender_type": "driver",
    "message_date": "2024-12-24 12:00:00"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Message tracking updated successfully",
    "data": {
        "commute_distance_id": 123,
        "sender_type": "driver",
        "message_tracking": {
            "id": 1,
            "commute_distance_id": 123,
            "passenger_last_message_date": "2024-12-24 10:30:00",
            "driver_last_message_date": "2024-12-24 12:00:00",
            "created_at": "2024-12-24 10:00:00",
            "updated_at": "2024-12-24 12:00:00"
        }
    }
}
```

### 4. Get Message Tracking Statistics

**GET** `/api/commute-distances/message-tracking/stats`

Get overall statistics about message tracking.

**Response:**
```json
{
    "success": true,
    "data": {
        "total_records": 150,
        "records_with_passenger_messages": 120,
        "records_with_driver_messages": 95,
        "avg_days_since_passenger_message": 2.5,
        "avg_days_since_driver_message": 3.2
    }
}
```

### 5. Get Recent Messages

**GET** `/api/commute-distances/message-tracking/recent?days=7`

Get commute distances with recent messages (within specified days).

**Response:**
```json
{
    "success": true,
    "data": {
        "days": 7,
        "recent_messages": [
            {
                "id": 1,
                "commute_distance_id": 123,
                "passenger_last_message_date": "2024-12-24 10:30:00",
                "driver_last_message_date": "2024-12-24 11:15:00",
                "updated_at": "2024-12-24 11:15:00"
            }
        ],
        "count": 1
    }
}
```

### 6. Get Inactive Messages

**GET** `/api/commute-distances/message-tracking/inactive?days=7`

Get commute distances with no recent messages (older than specified days).

**Response:**
```json
{
    "success": true,
    "data": {
        "days": 7,
        "inactive_messages": [
            {
                "id": 2,
                "commute_distance_id": 124,
                "passenger_last_message_date": "2024-12-15 10:30:00",
                "driver_last_message_date": "2024-12-16 11:15:00",
                "updated_at": "2024-12-16 11:15:00"
            }
        ],
        "count": 1
    }
}
```

## Usage Examples

### 1. Send a notification and track it

```bash
curl -X POST http://localhost:8000/api/commuters/user123/send-notification \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hi, I can give you a ride tomorrow",
    "title": "Ride Offer",
    "commute_distance_id": 123,
    "sender_type": "driver"
  }'
```

### 2. Get message tracking for a commute distance

```bash
curl -X GET http://localhost:8000/api/commute-distances/123/message-tracking
```

### 3. Update message tracking manually

```bash
curl -X PATCH http://localhost:8000/api/commute-distances/123/message-tracking \
  -H "Content-Type: application/json" \
  -d '{
    "sender_type": "passenger",
    "message_date": "2024-12-24 14:30:00"
  }'
```

## Implementation Details

### Service Methods

The `CommuteDistanceMessageTrackingService` provides these key methods:

- `updateMessageTracking(int $commuteDistanceId, string $senderType, ?DateTimeInterface $messageDate = null): bool`
- `getMessageTracking(int $commuteDistanceId): ?CommuteDistanceMessages`
- `getMessageTrackingStats(): array`
- `findWithRecentMessages(int $days = 7): array`
- `findWithNoRecentMessages(int $days = 7): array`
- `getMessageTrackingData(int $commuteDistanceId): ?array`

### Repository Methods

The `CommuteDistanceMessagesRepository` provides:

- `findByCommuteDistanceId(int $commuteDistanceId): ?CommuteDistanceMessages`
- `findOrCreateByCommuteDistanceId(int $commuteDistanceId): CommuteDistanceMessages`
- `updatePassengerLastMessageDate(int $commuteDistanceId, DateTimeInterface $date): void`
- `updateDriverLastMessageDate(int $commuteDistanceId, DateTimeInterface $date): void`

## Database Setup

### Option 1: Using Doctrine Migrations

```bash
php bin/console doctrine:migrations:migrate
```

### Option 2: Manual SQL

Run the SQL from `commute_distance_messages_table.sql`:

```sql
CREATE TABLE commute_distance_messages (
    id INT AUTO_INCREMENT NOT NULL,
    commute_distance_id INT NOT NULL,
    passenger_last_message_date DATETIME DEFAULT NULL,
    driver_last_message_date DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX commute_distance_idx (commute_distance_id),
    INDEX passenger_message_date_idx (passenger_last_message_date),
    INDEX driver_message_date_idx (driver_last_message_date)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
```

## Notes

- Message tracking is optional - notifications can be sent without tracking
- The system automatically creates tracking records when first needed
- Both passenger and driver message dates are tracked separately
- All timestamps are stored in UTC
- The system includes comprehensive logging for debugging 