# Scandal Article API Updates

## Overview
The `generateScandalArticle` endpoint has been enhanced to provide more comprehensive information about political scandals, including timelines and detailed information about involved persons.

## Changes Made

### 1. Enhanced AI Prompt
The OpenAI prompt has been updated to request:
- A detailed journalistic article (300-500 words) with witty, satirical tone
- A chronological timeline of key events in the scandal
- Full names (first and last names) for ALL politicians and persons involved
- Background, impact, and aftermath information

### 2. New Response Structure
The API now returns a structured JSON response instead of just plain text:

```json
{
  "article": "Detailed article text...",
  "timeline": [
    {
      "date": "2023-01-15",
      "event": "Initial allegations surfaced"
    },
    {
      "date": "2023-02-20", 
      "event": "Investigation launched"
    }
  ],
  "involved_persons": [
    {
      "full_name": "John Doe",
      "role": "Primary suspect",
      "position": "Minister of Finance"
    },
    {
      "full_name": "Jane Smith",
      "role": "Whistleblower", 
      "position": "Department Head"
    }
  ]
}
```

### 3. Database Storage
The enhanced data is now stored in the database:
- `article`: The main article text
- `timeline`: Array of chronological events with dates
- `involved_persons`: Array of persons involved with full names, roles, and positions

### 4. Caching Behavior
**NEW**: The endpoint now implements intelligent caching to avoid unnecessary AI calls:

- **First Request**: If no article exists for the specific scandal, the AI generates a new article
- **Subsequent Requests**: If an article already exists, the cached version is returned immediately without calling the AI
- **Cache Status**: The response includes a `cached` field indicating whether the article was retrieved from cache (`true`) or newly generated (`false`)

**Benefits:**
- Reduced API costs by avoiding duplicate AI calls
- Faster response times for cached articles
- Consistent article content for the same scandal

### 5. Backward Compatibility
- The API maintains backward compatibility
- If the AI response cannot be parsed as JSON, it falls back to storing just the article text
- Empty arrays are provided for timeline and involved_persons if parsing fails

## API Endpoint

**POST** `/api/politicians/scandals/article`

### Request Body
```json
{
  "politician": "John Doe",
  "country": "Test Country", 
  "scandal": {
    "title": "Test Scandal",
    "year": "2023",
    "description": "A test scandal description"
  }
}
```

### Response
```json
{
  "article": "Detailed article text...",
  "timeline": [...],
  "involved_persons": [...],
  "cached": false
}
```

### Response Fields
- `article`: The main article text (300-500 words)
- `timeline`: Array of chronological events with dates and descriptions
- `involved_persons`: Array of persons involved with full names, roles, and positions
- `cached`: Boolean indicating if the article was retrieved from cache (`true`) or newly generated (`false`)

## Implementation Details

### Caching Logic
1. The system first checks if a scandal document exists for the politician and country
2. It searches for the specific scandal by title and year
3. If the scandal has an existing article, it returns the cached version
4. If no article exists, it calls the AI to generate a new one and stores it
5. The response includes cache status for transparency

### Performance Benefits
- **Cost Savings**: Eliminates duplicate AI API calls for the same scandal
- **Speed**: Cached responses are returned instantly
- **Consistency**: Same article content for repeated requests
- **Reliability**: Reduces dependency on external AI service availability 