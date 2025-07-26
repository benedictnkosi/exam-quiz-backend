# Shady Meter API Documentation

This document provides comprehensive documentation for all API endpoints in the Shady Meter application.

## Base URL
```
http://localhost:3000/api
```

## Authentication
All endpoints require an OpenAI API key to be configured in the environment variables (`OPENAI_API_KEY`).

---

## 1. News API

### Endpoint: `/api/news`

#### POST - Fetch Trending Corruption News

Fetches the top 3 most significant corruption-related news stories for a specific country. Uses OpenAI with web search to get current news and caches results for the day.

**Request Body:**
```json
{
  "country": "string"
}
```

**Request Example:**
```bash
curl -X POST http://localhost:3000/api/news \
  -H "Content-Type: application/json" \
  -d '{"country": "Nigeria"}'
```

**Response:**
```json
{
  "news": [
    {
      "title": "Headline of the news story",
      "summary": "2-3 sentence summary of the story",
      "impact": "high|medium|low",
      "source": "News source if known"
    }
  ],
  "country": "Nigeria",
  "cached": false,
  "createdAt": "2024-01-15T10:30:00.000Z"
}
```

**Error Responses:**
- `400` - Missing or invalid country
- `500` - OpenAI API key not set
- `502` - OpenAI API error or parsing error

#### GET - Retrieve News

Retrieves news from the database with optional filtering.

**Query Parameters:**
- `country` (optional): Filter by country
- `todayOnly` (optional): Filter for today's news only (true/false)

**Request Example:**
```bash
curl "http://localhost:3000/api/news?country=Nigeria&todayOnly=true"
```

**Response:**
```json
[
  {
    "id": "document_id",
    "country": "Nigeria",
    "news": [...],
    "createdAt": "2024-01-15T10:30:00.000Z"
  }
]
```

---

## 2. Politicians API

### Endpoint: `/api/politicians`

#### POST - Generate Politicians List

Generates a list of 20 politicians to watch in a specific country based on corruption allegations using OpenAI.

**Request Body:**
```json
{
  "country": "string"
}
```

**Request Example:**
```bash
curl -X POST http://localhost:3000/api/politicians \
  -H "Content-Type: application/json" \
  -d '{"country": "South Africa"}'
```

**Response:**
```json
[
  {
    "id": "document_id",
    "fullName": "Politician Name",
    "country": "South Africa",
    "party": "Political Party",
    "position": "Current Position",
    "score": 85,
    "status": "active|retired|deceased",
    "note": "One-sentence reason for inclusion",
    "created": true
  }
]
```

**Error Responses:**
- `400` - Missing or invalid country
- `500` - OpenAI API key not set
- `502` - OpenAI API error or parsing error

#### GET - Retrieve Politicians

Retrieves politicians from the database, excluding trending politicians.

**Query Parameters:**
- `country` (required): Filter by country

**Request Example:**
```bash
curl "http://localhost:3000/api/politicians?country=South%20Africa"
```

**Response:**
```json
[
  {
    "id": "document_id",
    "fullName": "Politician Name",
    "country": "South Africa",
    "party": "Political Party",
    "position": "Current Position",
    "score": 85,
    "status": "active",
    "note": "One-sentence reason for inclusion",
    "createdAt": "2024-01-15T10:30:00.000Z"
  }
]
```

**Error Responses:**
- `400` - Missing or invalid country

---

## 3. Trending Politicians API

### Endpoint: `/api/politicians/trending`

#### POST - Generate Trending Politicians

Generates a list of 10 most trending politicians for corruption scandals in the current year using OpenAI with web search.

**Request Body:**
```json
{
  "country": "string"
}
```

**Request Example:**
```bash
curl -X POST http://localhost:3000/api/politicians/trending \
  -H "Content-Type: application/json" \
  -d '{"country": "Kenya"}'
```

**Response:**
```json
[
  {
    "id": "document_id",
    "fullName": "Politician Name",
    "country": "Kenya",
    "party": "Political Party",
    "position": "Current Position",
    "score": 95,
    "status": "active",
    "note": "One-sentence reason for trending",
    "trending": true,
    "created": true
  }
]
```

**Error Responses:**
- `400` - Missing or invalid country
- `500` - OpenAI API key not set
- `502` - OpenAI API error or parsing error

#### GET - Retrieve Trending Politicians

Retrieves trending politicians from the database.

**Query Parameters:**
- `country` (optional): Filter by country
- `todayOnly` (optional): Filter for today's entries only (true/false)

**Request Example:**
```bash
curl "http://localhost:3000/api/politicians/trending?country=Kenya&todayOnly=true"
```

**Response:**
```json
[
  {
    "id": "document_id",
    "fullName": "Politician Name",
    "country": "Kenya",
    "party": "Political Party",
    "position": "Current Position",
    "score": 95,
    "status": "active",
    "note": "One-sentence reason for trending",
    "trending": true,
    "createdAt": "2024-01-15T10:30:00.000Z"
  }
]
```

---

## 4. Scandals API

### Endpoint: `/api/politicians/scandals`

#### POST - Generate Politician Scandals

Generates a list of corruption-related scandals for a specific politician using OpenAI with web search.

**Request Body:**
```json
{
  "politician": "string",
  "country": "string"
}
```

**Request Example:**
```bash
curl -X POST http://localhost:3000/api/politicians/scandals \
  -H "Content-Type: application/json" \
  -d '{"politician": "Jacob Zuma", "country": "South Africa"}'
```

**Response:**
```json
{
  "id": "document_id",
  "politician": "Jacob Zuma",
  "country": "South Africa",
  "scandals": [
    {
      "title": "Specific scandal title",
      "year": 2023,
      "description": "One-sentence description with concrete details",
      "status": "proven|under_investigation|cleared|unresolved",
      "impactScore": 8
    }
  ],
  "totalCorruptionScore": 75
}
```

**Error Responses:**
- `400` - Missing or invalid politician or country
- `500` - OpenAI API key not set
- `502` - OpenAI API error or parsing error

#### GET - Retrieve Politician Scandals

Retrieves scandals for a specific politician from the database.

**Query Parameters:**
- `politician` (required): Politician name to search for

**Request Example:**
```bash
curl "http://localhost:3000/api/politicians/scandals?politician=Jacob%20Zuma"
```

**Response:**
```json
[
  {
    "id": "document_id",
    "politician": "Jacob Zuma",
    "country": "South Africa",
    "scandals": [
      {
        "title": "Specific scandal title",
        "year": 2023,
        "description": "One-sentence description with concrete details",
        "status": "proven",
        "impactScore": 8,
        "article": "Detailed article text (if generated)"
      }
    ],
    "totalCorruptionScore": 75,
    "createdAt": "2024-01-15T10:30:00.000Z"
  }
]
```

**Error Responses:**
- `400` - Missing politician query parameter

---

## 5. Scandal Article API

### Endpoint: `/api/politicians/scandals/article`

#### POST - Generate Scandal Article

Generates a detailed journalistic article (300-500 words) about a specific scandal using OpenAI.

**Request Body:**
```json
{
  "politician": "string",
  "country": "string",
  "scandal": {
    "title": "string",
    "year": "number",
    "description": "string"
  }
}
```

**Request Example:**
```bash
curl -X POST http://localhost:3000/api/politicians/scandals/article \
  -H "Content-Type: application/json" \
  -d '{
    "politician": "Jacob Zuma",
    "country": "South Africa",
    "scandal": {
      "title": "Nkandla Scandal",
      "year": 2014,
      "description": "Public funds used for private home improvements"
    }
  }'
```

**Response:**
```json
{
  "article": "Detailed 300-500 word journalistic article about the scandal..."
}
```

**Error Responses:**
- `400` - Missing required fields
- `404` - No scandal document found or scandal not found
- `500` - OpenAI API key not set
- `502` - OpenAI API error

---

## 6. Countries API

### Endpoint: `/api/countries`

#### GET - Get Country Statistics

Retrieves corruption statistics for countries, including scandal counts, active cases, and politician counts.

**Query Parameters:**
- `country` (optional): Filter by specific country

**Request Example:**
```bash
# Get all countries
curl "http://localhost:3000/api/countries"

# Get specific country
curl "http://localhost:3000/api/countries?country=South%20Africa"
```

**Response:**
```json
{
  "countries": [
    {
      "country": "South Africa",
      "scandals": 25,
      "activeCases": 8,
      "politicians": 15
    },
    {
      "country": "Nigeria",
      "scandals": 18,
      "activeCases": 5,
      "politicians": 12
    }
  ]
}
```

---

## Data Models

### Politician Object
```json
{
  "id": "string",
  "fullName": "string",
  "country": "string",
  "party": "string",
  "position": "string",
  "score": "number (1-100)",
  "status": "active|retired|deceased",
  "note": "string",
  "trending": "boolean (optional)",
  "createdAt": "timestamp",
  "updatedAt": "timestamp (optional)"
}
```

### Scandal Object
```json
{
  "title": "string",
  "year": "number",
  "description": "string",
  "status": "proven|under_investigation|cleared|unresolved",
  "impactScore": "number (1-10)",
  "article": "string (optional)"
}
```

### News Object
```json
{
  "title": "string",
  "summary": "string",
  "impact": "high|medium|low",
  "source": "string (optional)"
}
```

---

## Error Handling

All endpoints follow a consistent error response format:

```json
{
  "error": "Error message",
  "details": "Additional error details (optional)"
}
```

Common HTTP status codes:
- `200` - Success
- `400` - Bad Request (missing/invalid parameters)
- `404` - Not Found
- `500` - Internal Server Error
- `502` - Bad Gateway (OpenAI API errors)

---

## Rate Limiting & Caching

- News endpoints cache results for the current day to reduce API calls
- Politician and scandal data is stored in Firestore for persistence
- Trending politicians are marked with `trending: true` flag
- All endpoints include limits on database queries to reduce quota usage

---

## Environment Variables

Required environment variables:
- `OPENAI_API_KEY` - OpenAI API key for AI-powered content generation
- Firebase configuration (handled in `lib/firebase.ts`)

---

## Notes

1. All OpenAI-powered endpoints use web search capabilities for current information
2. Data is cached in Firestore to reduce API calls and improve performance
3. Error responses return empty arrays instead of errors to prevent UI issues
4. All timestamps are in ISO 8601 format
5. Country names should be provided in English
6. Politician names should be provided as they commonly appear in news sources 