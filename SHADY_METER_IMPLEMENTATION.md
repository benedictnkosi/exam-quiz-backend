# Shady Meter API Implementation

This document provides details about the implementation of the Shady Meter API in the Symfony application.

## Overview

The Shady Meter API has been implemented as a complete Symfony application with the following components:

- **Entities**: Database models for News, Politicians, and Politician Scandals
- **Repositories**: Data access layer for database operations
- **Services**: Business logic layer with OpenAI integration
- **Controllers**: API endpoints following RESTful conventions
- **Database**: MySQL tables with proper indexing

## Database Schema

### News Table
```sql
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country VARCHAR(100) NOT NULL,
    news JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX news_country_idx (country),
    INDEX news_created_at_idx (created_at)
);
```

### Politicians Table
```sql
CREATE TABLE politicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(200) NOT NULL,
    country VARCHAR(100) NOT NULL,
    party VARCHAR(200) NULL,
    position VARCHAR(200) NULL,
    score INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    note TEXT NULL,
    trending BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX politician_country_idx (country),
    INDEX politician_trending_idx (trending),
    INDEX politician_created_at_idx (created_at)
);
```

### Politician Scandals Table
```sql
CREATE TABLE politician_scandals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician VARCHAR(200) NOT NULL,
    country VARCHAR(100) NOT NULL,
    scandals JSON NOT NULL,
    total_corruption_score INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX scandal_politician_idx (politician),
    INDEX scandal_country_idx (country),
    INDEX scandal_created_at_idx (created_at)
);
```

## API Endpoints

### 1. News API

#### POST `/api/news`
- **Purpose**: Fetch trending corruption news for a specific country
- **Request Body**: `{"country": "string"}`
- **Response**: News array with caching information
- **Controller**: `NewsController::fetchNews()`

#### GET `/api/news`
- **Purpose**: Retrieve news from database with optional filtering
- **Query Parameters**: 
  - `country` (optional): Filter by country
  - `todayOnly` (optional): Filter for today's news only
- **Controller**: `NewsController::getNews()`

### 2. Politicians API

#### POST `/api/politicians`
- **Purpose**: Generate list of politicians to watch
- **Request Body**: `{"country": "string"}`
- **Response**: Array of politician objects
- **Controller**: `PoliticianController::generatePoliticians()`

#### GET `/api/politicians`
- **Purpose**: Retrieve politicians from database
- **Query Parameters**: `country` (required)
- **Controller**: `PoliticianController::getPoliticians()`

#### POST `/api/politicians/trending`
- **Purpose**: Generate trending politicians
- **Request Body**: `{"country": "string"}`
- **Response**: Array of trending politician objects
- **Controller**: `PoliticianController::generateTrendingPoliticians()`

#### GET `/api/politicians/trending`
- **Purpose**: Retrieve trending politicians
- **Query Parameters**: 
  - `country` (optional)
  - `todayOnly` (optional)
- **Controller**: `PoliticianController::getTrendingPoliticians()`

### 3. Scandals API

#### POST `/api/politicians/scandals`
- **Purpose**: Generate scandals for a specific politician
- **Request Body**: `{"politician": "string", "country": "string"}`
- **Response**: Scandal object with total corruption score and province information
- **Controller**: `PoliticianScandalController::generatePoliticianScandals()`

**Scandal Structure:**
```json
{
  "scandals": [
    {
      "title": "string",
      "year": "number", 
      "description": "string",
      "status": "proven|under_investigation|cleared|unresolved",
      "impactScore": "number (1-10)",
      "province": "string (specific province/region or 'National')",
      "sector": "string (e.g., energy, education, health, defense, infrastructure, agriculture, finance, transportation, telecommunications, etc.)"
    }
  ],
  "totalCorruptionScore": "number"
}
```

#### GET `/api/politicians/scandals`
- **Purpose**: Retrieve scandals for a politician
- **Query Parameters**: `politician` (required)
- **Controller**: `PoliticianScandalController::getPoliticianScandals()`

#### POST `/api/politicians/scandals/article`
- **Purpose**: Generate detailed article about a scandal
- **Request Body**: 
  ```json
  {
    "politician": "string",
    "country": "string",
    "scandal": {
      "title": "string",
      "year": "number",
      "description": "string",
      "province": "string (optional, defaults to 'National')",
      "sector": "string (optional, defaults to 'general')"
    }
  }
  ```
- **Controller**: `PoliticianScandalController::generateScandalArticle()`

### 4. Countries API

#### GET `/api/countries`
- **Purpose**: Get corruption statistics for countries
- **Query Parameters**: `country` (optional)
- **Response**: Statistics including scandal counts and politician counts
- **Controller**: `CountryController::getCountryStatistics()`

## Implementation Details

### Entities

1. **News Entity** (`src/Entity/News.php`)
   - Stores news articles as JSON
   - Includes country and timestamp information
   - Supports caching by date

2. **Politician Entity** (`src/Entity/Politician.php`)
   - Stores politician information
   - Includes corruption score and trending flag
   - Supports status tracking (active/retired/deceased)

3. **PoliticianScandal Entity** (`src/Entity/PoliticianScandal.php`)
   - Stores scandals as JSON array
   - Includes total corruption score
   - Links to politician and country

### Repositories

1. **NewsRepository** (`src/Repository/NewsRepository.php`)
   - `findByCountry()`: Get news by country
   - `findByCountryAndToday()`: Get today's news for a country
   - `findTodayOnly()`: Get all today's news

2. **PoliticianRepository** (`src/Repository/PoliticianRepository.php`)
   - `findByCountry()`: Get non-trending politicians by country
   - `findTrendingByCountry()`: Get trending politicians by country
   - `findTrendingTodayOnly()`: Get today's trending politicians

3. **PoliticianScandalRepository** (`src/Repository/PoliticianScandalRepository.php`)
   - `findByPolitician()`: Get scandals for a politician
   - `getCountryStatistics()`: Get corruption statistics by country

### Services

**ShadyMeterService** (`src/Service/ShadyMeterService.php`)
- Central business logic for all API operations
- Handles OpenAI integration (currently mocked)
- Manages data persistence and retrieval
- Implements caching logic for news

### Controllers

All controllers follow Symfony best practices:
- Use dependency injection
- Implement proper error handling
- Return consistent JSON responses
- Include comprehensive validation

## Configuration

### Environment Variables
```env
OPENAI_API_KEY=your_openai_api_key_here
```

### Services Configuration
The `ShadyMeterService` is configured in `config/services.yaml`:
```yaml
App\Service\ShadyMeterService:
    arguments:
        $openaiApiKey: "%openai_api_key%"
```

## Error Handling

All endpoints implement consistent error handling:
- **400**: Bad Request (missing/invalid parameters)
- **404**: Not Found
- **500**: Internal Server Error
- **502**: Bad Gateway (OpenAI API errors)

Error responses follow this format:
```json
{
  "error": "Error message",
  "details": "Additional error details (optional)"
}
```

## Caching Strategy

- News endpoints cache results for the current day
- Database queries are optimized with proper indexing
- Trending politicians are marked with a boolean flag
- All timestamps are stored in ISO 8601 format

## Security Considerations

- All endpoints require OpenAI API key configuration
- Input validation is implemented for all parameters
- SQL injection is prevented through Doctrine ORM
- JSON responses are properly sanitized

## Future Enhancements

1. **OpenAI Integration**: Replace mock data with actual OpenAI API calls
2. **Rate Limiting**: Implement API rate limiting
3. **Authentication**: Add user authentication if required
4. **Caching**: Implement Redis caching for better performance
5. **Monitoring**: Add logging and monitoring capabilities
6. **Testing**: Add comprehensive unit and integration tests

## Usage Examples

### Generate News for Nigeria
```bash
curl -X POST http://localhost:8000/api/news \
  -H "Content-Type: application/json" \
  -d '{"country": "Nigeria"}'
```

### Get Politicians for South Africa
```bash
curl "http://localhost:8000/api/politicians?country=South%20Africa"
```

### Generate Scandals for a Politician
```bash
curl -X POST http://localhost:8000/api/politicians/scandals \
  -H "Content-Type: application/json" \
  -d '{"politician": "Jacob Zuma", "country": "South Africa"}'
```

### Get Country Statistics
```bash
curl "http://localhost:8000/api/countries?country=Kenya"
```

## Database Migration

To create the database tables, run the SQL file:
```bash
mysql -u your_user -p your_database < migrations/create_shady_meter_tables.sql
```

## Testing

The implementation includes mock data for testing purposes. To test the endpoints:

1. Start the Symfony development server
2. Use the provided curl examples
3. Verify the responses match the expected format
4. Check that data is properly stored in the database

## Notes

- All timestamps are in UTC
- Country names should be provided in English
- Politician names should match common news sources
- The implementation is ready for production with proper OpenAI integration 