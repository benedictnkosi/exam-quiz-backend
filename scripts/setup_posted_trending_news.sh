#!/bin/bash

# Setup Posted Trending News Tracking System
# This script creates the database table for tracking posted trending news

echo "Setting up Posted Trending News Tracking System..."

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "Error: Please run this script from the project root directory"
    exit 1
fi

# Database configuration
DB_HOST=${DB_HOST:-"localhost"}
DB_PORT=${DB_PORT:-"3306"}
DB_NAME=${DB_NAME:-"exam_quiz_backend"}
DB_USER=${DB_USER:-"root"}
DB_PASS=${DB_PASS:-""}

echo "Database: ${DB_NAME} on ${DB_HOST}:${DB_PORT}"

# Create the posted_trending_news table
echo "Creating posted_trending_news table..."

if [ -z "$DB_PASS" ]; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" < create_posted_trending_news_table.sql
else
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < create_posted_trending_news_table.sql
fi

if [ $? -eq 0 ]; then
    echo "✅ posted_trending_news table created successfully"
else
    echo "❌ Failed to create posted_trending_news table"
    exit 1
fi

# Clear cache
echo "Clearing Symfony cache..."
php bin/console cache:clear

if [ $? -eq 0 ]; then
    echo "✅ Cache cleared successfully"
else
    echo "⚠️  Warning: Failed to clear cache"
fi

# Test the system
echo "Testing the tracking system..."
php bin/console app:test-posted-trending-news --action=stats --scope=global

if [ $? -eq 0 ]; then
    echo "✅ Tracking system test completed successfully"
else
    echo "⚠️  Warning: Tracking system test failed (this is normal if no data exists yet)"
fi

echo ""
echo "🎉 Posted Trending News Tracking System setup complete!"
echo ""
echo "Next steps:"
echo "1. Test the system: php bin/console app:test-posted-trending-news --action=check --politician='John Doe'"
echo "2. Generate news: php bin/console app:test-posted-trending-news --action=generate --scope=global"
echo "3. View API: curl 'http://localhost:3000/api/posted-trending-news/statistics?scope=global'"
echo ""
echo "The system will now prevent posting the same politician twice in a month!" 