# Twitter News API Documentation

This document provides comprehensive documentation for the Twitter news functionality that generates and posts global trending news summaries to Twitter.

## Overview

The Twitter news system consists of:
- **TwitterNewsService**: Core service for generating Twitter summaries and posting tweets
- **PostTwitterNewsCommand**: Symfony command for CLI execution
- **TwitterNewsController**: REST API endpoints for web integration

## Features

- Generate Twitter-friendly summaries (max 280 characters) from global trending news
- Post summaries to Twitter using OAuth 1.0 authentication
- Support for both generating new news and using existing cached news
- Rate limiting protection with delays between tweets
- Comprehensive error handling and logging
- Dry-run mode for testing without posting

## Environment Variables

Ensure these Twitter API credentials are set in your environment:

```env
X_API_KEY=your_twitter_api_key
X_API_SECRET=your_twitter_api_secret
X_TOKEN=your_twitter_access_token
X_TOKEN_SECRET=your_twitter_access_token_secret
```

## Command Line Usage

### Basic Command

```bash
# Generate new global news and post all summaries to Twitter
php bin/console app:post-twitter-news

# Generate news for specific region
php bin/console app:post-twitter-news africa
php bin/console app:post-twitter-news usa
php bin/console app:post-twitter-news europe
php bin/console app:post-twitter-news asia

# Use existing news instead of generating new ones
php bin/console app:post-twitter-news --use-existing
php bin/console app:post-twitter-news africa --use-existing

# Dry run - generate summaries without posting
php bin/console app:post-twitter-news --dry-run
php bin/console app:post-twitter-news usa --dry-run

# Post only one summary instead of all
php bin/console app:post-twitter-news --single
php bin/console app:post-twitter-news europe --single

# Combine options
php bin/console app:post-twitter-news --use-existing --dry-run
php bin/console app:post-twitter-news africa --use-existing --single
```

### Command Arguments

- `scope` (optional): Geographic scope for news (global, africa, usa, europe, asia, etc.) [default: "global"]

### Command Options

- `--dry-run, -d`: Generate summaries without posting to Twitter
- `--use-existing, -x`: Use existing news instead of generating new ones
- `--single, -s`: Post only one summary instead of all

## API Endpoints

### Base URL
```
/api/twitter-news
```

### 1. Generate Twitter Summaries

**POST** `/api/twitter-news/generate-summaries`

Generates Twitter-friendly summaries from trending news without posting them.

**Request Body:**
```json
{
  "useExisting": false,
  "scope": "global"
}
```

**Response:**
```json
{
  "success": true,
  "summaries": [
    {
      "originalStory": {
        "key": "story_1",
        "title": "Corruption scandal in government",
        "summary": "Detailed summary...",
        "impact": "high",
        "source": "Reuters"
      },
      "twitterSummary": "🚨 Corruption scandal in government | Reuters #CorruptionNews #GlobalTrending",
      "characterCount": 89
    }
  ],
  "totalCount": 1
}
```

### 2. Generate Fresh Twitter Summaries

**POST** `/api/twitter-news/generate-fresh-summaries`

Generates fresh Twitter-friendly summary from the top 1 trending news story using AI without saving to database or using cached news.

**Request Body:**
```json
{
  "scope": "global"
}
```

**Response:**
```json
{
  "success": true,
  "summaries": [
    {
      "originalStory": {
        "key": "story_1",
        "title": "Corruption scandal in government",
        "summary": "Detailed summary...",
        "impact": "high",
        "source": "Reuters"
      },
      "twitterSummary": "🚨 Corruption scandal in government | Reuters #CorruptionNews #GlobalTrending",
      "characterCount": 89
    }
  ],
  "totalCount": 1,
  "generatedAt": "2024-01-15T10:30:00.000Z",
  "dateRange": {
    "start": "January 8, 2024",
    "end": "January 15, 2024"
  }
}
```

### 3. Post Single Summary

**POST** `/api/twitter-news/post-summary`

Posts a single Twitter summary to Twitter.

**Request Body:**
```json
{
  "summary": "🚨 Corruption scandal in government | Reuters #CorruptionNews #GlobalTrending"
}
```

**Response:**
```json
{
  "success": true,
  "tweetId": "1234567890123456789",
  "summary": "🚨 Corruption scandal in government | Reuters #CorruptionNews #GlobalTrending",
  "characterCount": 89
}
```

### 4. Generate and Post All Summaries

**POST** `/api/twitter-news/generate-and-post`

Generates Twitter summaries from trending news and posts them all to Twitter.

**Request Body:**
```json
{
  "scope": "global",
  "useExisting": false
}
```

**Response:**
```json
{
  "success": true,
  "postedTweets": [
    {
      "success": true,
      "tweetId": "1234567890123456789",
      "summary": "🚨 Corruption scandal in government | Reuters #CorruptionNews #GlobalTrending",
      "characterCount": 89
    }
  ],
  "failedTweets": [],
  "totalGenerated": 1,
  "totalPosted": 1,
  "totalFailed": 0
}
```

### 5. Get Latest Summaries

**GET** `/api/twitter-news/latest-summaries`

Retrieves the latest global news and generates Twitter summaries without posting.

**Response:**
```json
{
  "success": true,
  "summaries": [
    {
      "originalStory": {
        "key": "story_1",
        "title": "Corruption scandal in government",
        "summary": "Detailed summary...",
        "impact": "high",
        "source": "Reuters"
      },
      "twitterSummary": "🚨 Corruption scandal in government | Reuters #CorruptionNews #GlobalTrending",
      "characterCount": 89
    }
  ],
  "newsId": 123,
  "createdAt": "2024-01-15T10:30:00.000Z"
}
```

### 6. Test Twitter Connection

**POST** `/api/twitter-news/test-connection`

Tests the Twitter API connection by posting a test tweet.

**Response:**
```json
{
  "success": true,
  "message": "Twitter connection test successful",
  "tweetId": "1234567890123456789",
  "testMessage": "🧪 Test tweet from ShadyMeter API - 2024-01-15 10:30:00 #TestTweet"
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
  "error": "Error message",
  "details": "Additional error details"
}
```

Common HTTP status codes:
- `400`: Bad Request (missing/invalid parameters)
- `404`: Not Found (no news available)
- `500`: Internal Server Error
- `502`: Bad Gateway (Twitter API errors)

## Summary Format

Twitter summaries follow this format:
- **Impact emoji** (🚨 for high, ⚠️ for medium, 📰 for low)
- **Title** (truncated if too long)
- **Source** (if available and space permits)
- **Hashtags** (#CorruptionNews #GlobalTrending)

Example:
```
🚨 Major corruption scandal uncovered in government involving millions in bribes | Reuters #CorruptionNews #GlobalTrending
```

## Rate Limiting

The service includes built-in rate limiting:
- 2-second delay between tweets to avoid Twitter API rate limits
- Automatic retry logic for transient errors
- Comprehensive error logging for failed tweets

## Logging

All operations are logged with appropriate levels:
- `INFO`: Successful operations
- `WARNING`: Non-critical issues
- `ERROR`: Failed operations and API errors

## Security Considerations

- Twitter API credentials are stored as environment variables
- OAuth 1.0 authentication is used for Twitter API calls
- Input validation prevents malicious content
- Character limits are enforced to prevent API errors

## Usage Examples

### Using cURL

```bash
# Generate summaries
curl -X POST http://localhost:8000/api/twitter-news/generate-summaries \
  -H "Content-Type: application/json" \
  -d '{"useExisting": false, "scope": "global"}'

# Generate summaries for specific region
curl -X POST http://localhost:8000/api/twitter-news/generate-summaries \
  -H "Content-Type: application/json" \
  -d '{"useExisting": false, "scope": "africa"}'

# Generate fresh summaries (no database save)
curl -X POST http://localhost:8000/api/twitter-news/generate-fresh-summaries \
  -H "Content-Type: application/json" \
  -d '{"scope": "usa"}'

# Post a single summary
curl -X POST http://localhost:8000/api/twitter-news/post-summary \
  -H "Content-Type: application/json" \
  -d '{"summary": "🚨 Test corruption news #CorruptionNews"}'

# Generate and post all summaries
curl -X POST http://localhost:8000/api/twitter-news/generate-and-post \
  -H "Content-Type: application/json" \
  -d '{"useExisting": true, "scope": "global"}'

# Generate and post summaries for specific region
curl -X POST http://localhost:8000/api/twitter-news/generate-and-post \
  -H "Content-Type: application/json" \
  -d '{"useExisting": false, "scope": "europe"}'
```

### Using JavaScript/Fetch

```javascript
// Generate summaries
const response = await fetch('/api/twitter-news/generate-summaries', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    useExisting: false
  })
});

const result = await response.json();
console.log(result.summaries);
```

## Troubleshooting

### Common Issues

1. **Twitter API Authentication Errors**
   - Verify all Twitter API credentials are set correctly
   - Ensure the Twitter app has write permissions
   - Check if the access tokens are valid

2. **Character Limit Exceeded**
   - Summaries are automatically truncated to 280 characters
   - Check the `characterCount` field in responses

3. **No News Available**
   - Use `useExisting: false` to generate new news
   - Check if global news generation is working

4. **Rate Limiting**
   - The service includes automatic delays between tweets
   - If you encounter rate limits, increase the delay in the service

### Debug Mode

Enable debug logging by setting the log level to DEBUG in your Symfony configuration.

## Future Enhancements

1. **Scheduled Posting**: Add cron job integration for automatic posting
2. **Multiple Accounts**: Support for posting to multiple Twitter accounts
3. **Content Filtering**: Add content moderation and filtering
4. **Analytics**: Track tweet performance and engagement
5. **Custom Hashtags**: Allow configurable hashtags per post
6. **Media Support**: Add support for images and videos in tweets 