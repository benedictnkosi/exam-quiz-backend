# Tender API Documentation

This document describes the API endpoints for managing tenders in the system.

## Base URL
```
/api/tenders
```

## Endpoints

### 1. Get All Tenders
**GET** `/api/tenders`

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Number of items per page (default: 10)
- `category` (optional): Filter by category
- `province` (optional): Filter by province
- `organOfState` (optional): Filter by organ of state

**Response:**
```json
{
    "success": true,
    "data": {
        "tenders": [...],
        "total": 100,
        "page": 1,
        "limit": 10,
        "totalPages": 10
    },
    "message": "Tenders retrieved successfully"
}
```

### 2. Get Tender by ID
**GET** `/api/tenders/{id}`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "category": "Construction",
        "tenderDescription": "Building construction project",
        "advertisedAt": "2024-01-15 10:00:00",
        "awardedAt": null,
        "tenderNumber": "TEN-2024-001",
        "organOfState": "Department of Public Works",
        "province": "Gauteng",
        "closingDate": "2024-12-31 23:59:59",
        "placeWhereGoodsWorksOrServicesAreRequired": "Pretoria, Gauteng",
        "contactPerson": "John Doe",
        "email": "john.doe@example.com",
        "telephoneNumber": "+27 12 345 6789",
        "successfulBidders": [],
        "created": "2024-01-15 10:00:00",
        "updated": "2024-01-15 10:00:00"
    },
    "message": "Tender retrieved successfully"
}
```

### 3. Create Tender
**POST** `/api/tenders`

**Request Body:**
```json
{
    "category": "Construction",
    "tenderDescription": "Building construction project for new office complex",
    "advertisedAt": "2024-01-15 10:00:00",
    "awardedAt": null,
    "tenderNumber": "TEN-2024-001",
    "organOfState": "Department of Public Works",
    "province": "Gauteng",
    "closingDate": "2024-12-31 23:59:59",
    "placeWhereGoodsWorksOrServicesAreRequired": "Pretoria, Gauteng",
    "contactPerson": "John Doe",
    "email": "john.doe@example.com",
    "telephoneNumber": "+27 12 345 6789",
    "successfulBidders": []
}
```

**Required Fields:**
- `category`
- `tenderDescription`
- `tenderNumber`
- `organOfState`
- `province`
- `closingDate`
- `placeWhereGoodsWorksOrServicesAreRequired`
- `contactPerson`
- `email`
- `telephoneNumber`

**Optional Fields:**
- `advertisedAt`
- `awardedAt`
- `successfulBidders`

### 4. Update Tender
**PUT** `/api/tenders/{id}`

**Request Body:** Same as create tender (all fields optional for update)

### 5. Delete Tender
**DELETE** `/api/tenders/{id}`

### 6. Get Tenders by Category
**GET** `/api/tenders/category/{category}`

### 7. Get Tenders by Province
**GET** `/api/tenders/province/{province}`

### 8. Get Active Tenders
**GET** `/api/tenders/active`

Returns tenders that haven't closed yet (closing date is in the future).

### 9. Get Awarded Tenders
**GET** `/api/tenders/awarded`

Returns tenders that have been awarded (awardedAt is not null).

### 10. Add Successful Bidder
**POST** `/api/tenders/{id}/add-bidder`

**Request Body:**
```json
{
    "bidder": "ABC Construction Company"
}
```

## Data Types

### Tender Entity Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | No | Auto-generated ID |
| `category` | string | Yes | Tender category (e.g., Construction, IT, Services) |
| `tenderDescription` | text | Yes | Detailed description of the tender |
| `advertisedAt` | datetime | No | When the tender was advertised |
| `awardedAt` | datetime | No | When the tender was awarded |
| `tenderNumber` | string | Yes | Unique tender number |
| `organOfState` | string | Yes | Government department or organ |
| `province` | string | Yes | Province where tender is located |
| `closingDate` | datetime | Yes | Deadline for tender submissions |
| `placeWhereGoodsWorksOrServicesAreRequired` | text | Yes | Location where work/services are needed |
| `contactPerson` | string | Yes | Contact person name |
| `email` | string | Yes | Contact email address |
| `telephoneNumber` | string | Yes | Contact phone number |
| `successfulBidders` | array | No | Array of successful bidder names |
| `created` | datetime | No | Record creation timestamp |
| `updated` | datetime | No | Record last update timestamp |

## Error Responses

All endpoints return error responses in the following format:

```json
{
    "success": false,
    "message": "Error description"
}
```

Common HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request (validation errors)
- `404` - Not Found
- `500` - Internal Server Error

## Example Usage

### Creating a New Tender
```bash
curl -X POST http://localhost:8000/api/tenders \
  -H "Content-Type: application/json" \
  -d '{
    "category": "IT Services",
    "tenderDescription": "Development of web application for government portal",
    "tenderNumber": "TEN-2024-002",
    "organOfState": "Department of Communications",
    "province": "Western Cape",
    "closingDate": "2024-06-30 23:59:59",
    "placeWhereGoodsWorksOrServicesAreRequired": "Cape Town, Western Cape",
    "contactPerson": "Jane Smith",
    "email": "jane.smith@example.com",
    "telephoneNumber": "+27 21 123 4567"
  }'
```

### Getting Tenders with Filters
```bash
curl "http://localhost:8000/api/tenders?category=Construction&province=Gauteng&page=1&limit=5"
```

### Adding a Successful Bidder
```bash
curl -X POST http://localhost:8000/api/tenders/1/add-bidder \
  -H "Content-Type: application/json" \
  -d '{
    "bidder": "XYZ Construction Ltd"
  }'
``` 