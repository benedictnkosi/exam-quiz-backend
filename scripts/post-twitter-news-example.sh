#!/bin/bash

# Example script demonstrating Twitter News functionality
# This script shows different ways to use the Twitter news posting feature

echo "🐦 Twitter News Posting Examples"
echo "==============================="

# Example 1: Dry run to see what would be posted
echo -e "\n📝 Example 1: Dry run (no actual posting)"
echo "This will generate summaries without posting to Twitter:"
echo "php bin/console app:post-twitter-news --dry-run"
echo ""

# Example 2: Use existing news instead of generating new ones
echo -e "📰 Example 2: Use existing global news"
echo "This will use cached global news instead of generating new ones:"
echo "php bin/console app:post-twitter-news --use-existing"
echo ""

# Example 3: Post only one summary
echo -e "🔤 Example 3: Post single summary"
echo "This will post only one summary instead of all:"
echo "php bin/console app:post-twitter-news --single"
echo ""

# Example 4: Combine options
echo -e "🔄 Example 4: Combine options"
echo "Use existing news and post only one summary:"
echo "php bin/console app:post-twitter-news --use-existing --single"
echo ""

# Example 5: Full posting
echo -e "🚀 Example 5: Full posting"
echo "Generate new global news and post all summaries:"
echo "php bin/console app:post-twitter-news"
echo ""

# Example 6: API usage
echo -e "🌐 Example 6: API endpoints"
echo "Test the API endpoints:"
echo ""
echo "# Get latest summaries"
echo "curl -X GET http://localhost:8000/api/twitter-news/latest-summaries"
echo ""
echo "# Generate summaries"
echo "curl -X POST http://localhost:8000/api/twitter-news/generate-summaries \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"useExisting\": false}'"
echo ""
echo "# Post single summary"
echo "curl -X POST http://localhost:8000/api/twitter-news/post-summary \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"summary\": \"🚨 Test corruption news #CorruptionNews\"}'"
echo ""
echo "# Generate and post all summaries"
echo "curl -X POST http://localhost:8000/api/twitter-news/generate-and-post \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"useExisting\": true}'"
echo ""

# Example 7: Cron job setup
echo -e "⏰ Example 7: Cron job setup"
echo "Add this to your crontab to post daily at 9 AM:"
echo "0 9 * * * cd /path/to/your/project && php bin/console app:post-twitter-news --use-existing"
echo ""

# Example 8: Environment setup
echo -e "🔧 Example 8: Environment setup"
echo "Add these to your .env file:"
echo ""
echo "X_API_KEY=your_twitter_api_key"
echo "X_API_SECRET=your_twitter_api_secret"
echo "X_TOKEN=your_twitter_access_token"
echo "X_TOKEN_SECRET=your_twitter_access_token_secret"
echo ""

echo -e "🎯 Quick Start Guide:"
echo "1. Set up Twitter API credentials in your environment"
echo "2. Test with dry run: php bin/console app:post-twitter-news --dry-run"
echo "3. Post a single tweet: php bin/console app:post-twitter-news --single"
echo "4. Post all summaries: php bin/console app:post-twitter-news"
echo ""

echo -e "📚 For more information, see TWITTER_NEWS_API.md" 