# Tender Bid Winners and Company Directors API Documentation

This document describes the API endpoints for managing tender bid winners and company directors.

## Tender Bid Winners API

### Base URL
```
/api/tender-bid-winners
```

### Endpoints

#### 1. Get All Tender Bid Winners
**GET** `/api/tender-bid-winners`

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Number of items per page (default: 10)
- `tenderId` (optional): Filter by tender ID
- `companyId` (optional): Filter by company ID

**Response:**
```json
{
    "success": true,
    "data": {
        "bidWinners": [
            {
                "id": 1,
                "tenderId": 1,
                "companyId": 1,
                "companyName": "ABC Construction Ltd",
                "created": "2024-01-15 10:00:00",
                "updated": "2024-01-15 10:00:00"
            }
        ],
        "total": 100,
        "page": 1,
        "limit": 10,
        "totalPages": 10
    },
    "message": "Tender bid winners retrieved successfully"
}
```

#### 2. Get Tender Bid Winner by ID
**GET** `/api/tender-bid-winners/{id}`

#### 3. Create Tender Bid Winner
**POST** `/api/tender-bid-winners`

**Request Body:**
```json
{
    "tenderId": 1,
    "companyId": 1,
    "companyName": "ABC Construction Ltd"
}
```

**Required Fields:**
- `tenderId` - ID of the tender
- `companyId` - ID of the company
- `companyName` - Name of the company

#### 4. Update Tender Bid Winner
**PUT** `/api/tender-bid-winners/{id}`

**Request Body:**
```json
{
    "companyName": "Updated Company Name"
}
```

#### 5. Delete Tender Bid Winner
**DELETE** `/api/tender-bid-winners/{id}`

#### 6. Get Bid Winners by Tender
**GET** `/api/tender-bid-winners/tender/{tenderId}`

#### 7. Get Bid Winners by Company
**GET** `/api/tender-bid-winners/company/{companyId}`

#### 8. Get Winning Companies
**GET** `/api/tender-bid-winners/winning-companies`

#### 9. Get Recent Bid Winners
**GET** `/api/tender-bid-winners/recent`

**Query Parameters:**
- `limit` (optional): Number of recent winners (default: 10)

## Company Directors API

### Base URL
```
/api/company-directors
```

### Endpoints

#### 1. Get All Company Directors
**GET** `/api/company-directors`

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Number of items per page (default: 10)
- `companyId` (optional): Filter by company ID
- `directorName` (optional): Filter by director name

**Response:**
```json
{
    "success": true,
    "data": {
        "directors": [
            {
                "id": 1,
                "directorName": "John Doe",
                "directorIdNumber": "8001015009087",
                "companyId": 1,
                "created": "2024-01-15 10:00:00",
                "updated": "2024-01-15 10:00:00"
            }
        ],
        "total": 50,
        "page": 1,
        "limit": 10,
        "totalPages": 5
    },
    "message": "Company directors retrieved successfully"
}
```

#### 2. Get Company Director by ID
**GET** `/api/company-directors/{id}`

#### 3. Create Company Director
**POST** `/api/company-directors`

**Request Body:**
```json
{
    "directorName": "John Doe",
    "directorIdNumber": "8001015009087",
    "companyId": 1
}
```

**Required Fields:**
- `directorName` - Full name of the director
- `directorIdNumber` - South African ID number
- `companyId` - ID of the company

#### 4. Update Company Director
**PUT** `/api/company-directors/{id}`

**Request Body:**
```json
{
    "directorName": "John Smith",
    "directorIdNumber": "8001015009087"
}
```

#### 5. Delete Company Director
**DELETE** `/api/company-directors/{id}`

#### 6. Get Directors by Company
**GET** `/api/company-directors/company/{companyId}`

#### 7. Search Directors by Name
**GET** `/api/company-directors/search/name/{directorName}`

#### 8. Get Director by ID Number
**GET** `/api/company-directors/search/id-number/{idNumber}`

#### 9. Get Unique Director Names
**GET** `/api/company-directors/unique-names`

#### 10. Get Directors of Multiple Companies
**GET** `/api/company-directors/multiple-companies`

#### 11. Get Recent Directors
**GET** `/api/company-directors/recent`

**Query Parameters:**
- `limit` (optional): Number of recent directors (default: 10)

## Data Types

### TenderBidWinner Entity Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | No | Auto-generated ID |
| `tenderId` | integer | Yes | ID of the tender |
| `companyId` | integer | Yes | ID of the company |
| `companyName` | string | Yes | Name of the company |
| `created` | datetime | No | Record creation timestamp |
| `updated` | datetime | No | Record last update timestamp |

### CompanyDirector Entity Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | No | Auto-generated ID |
| `directorName` | string | Yes | Full name of the director |
| `directorIdNumber` | string | Yes | South African ID number |
| `companyId` | integer | Yes | ID of the company |
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

### Creating a Tender Bid Winner
```bash
curl -X POST http://localhost:8000/api/tender-bid-winners \
  -H "Content-Type: application/json" \
  -d '{
    "tenderId": 1,
    "companyId": 1,
    "companyName": "ABC Construction Ltd"
  }'
```

### Creating a Company Director
```bash
curl -X POST http://localhost:8000/api/company-directors \
  -H "Content-Type: application/json" \
  -d '{
    "directorName": "John Doe",
    "directorIdNumber": "8001015009087",
    "companyId": 1
  }'
```

### Getting Bid Winners for a Specific Tender
```bash
curl "http://localhost:8000/api/tender-bid-winners/tender/1"
```

### Getting Directors for a Specific Company
```bash
curl "http://localhost:8000/api/company-directors/company/1"
```

### Searching Directors by Name
```bash
curl "http://localhost:8000/api/company-directors/search/name/John"
```

### Getting Directors of Multiple Companies
```bash
curl "http://localhost:8000/api/company-directors/multiple-companies"
```

## Database Relationships

### TenderBidWinner
- **ManyToOne** relationship with `Tender` entity
- **ManyToOne** relationship with `Company` entity
- Foreign key constraints ensure data integrity

### CompanyDirector
- **ManyToOne** relationship with `Company` entity
- Foreign key constraints ensure data integrity

## Business Rules

### TenderBidWinner
1. A company can only win a specific tender once
2. Both tender and company must exist before creating a bid winner
3. Company name is stored for historical reference

### CompanyDirector
1. A director can only be associated with a company once with a specific ID number
2. Company must exist before creating a director
3. ID number format validation (South African ID number format)
4. Directors can be associated with multiple companies

## Database Indexes

### TenderBidWinner Table
- `tender_bid_winner_tender_idx` on `tender_id`
- `tender_bid_winner_company_idx` on `company_id`

### CompanyDirector Table
- `company_director_company_idx` on `company_id`
- `company_director_id_number_idx` on `director_id_number`

## Migration

To create the database tables, run:

```bash
php bin/console doctrine:migrations:migrate
```

This will create:
- `tender_bid_winners` table
- `company_directors` table
- All necessary indexes and foreign key constraints 