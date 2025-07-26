#!/bin/bash

# Test script for Twitter News functionality
# This script tests the command line interface and API endpoints

echo "🧪 Testing Twitter News Functionality"
echo "====================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
    fi
}

# Test 1: Dry run command
echo -e "\n${YELLOW}Test 1: Dry run command${NC}"
php bin/console app:post-twitter-news --dry-run
print_status $? "Dry run command execution"

# Test 2: Generate summaries only (no posting)
echo -e "\n${YELLOW}Test 2: Generate summaries only${NC}"
php bin/console app:post-twitter-news --dry-run --use-existing
print_status $? "Generate summaries with existing news"

# Test 3: Test API endpoint (if server is running)
echo -e "\n${YELLOW}Test 3: API endpoint test${NC}"
if curl -s http://localhost:8000/api/twitter-news/latest-summaries > /dev/null 2>&1; then
    echo "API server is running, testing endpoints..."
    
    # Test latest summaries endpoint
    curl -s http://localhost:8000/api/twitter-news/latest-summaries | jq '.' > /dev/null 2>&1
    print_status $? "Latest summaries API endpoint"
    
    # Test generate summaries endpoint
    curl -s -X POST http://localhost:8000/api/twitter-news/generate-summaries \
        -H "Content-Type: application/json" \
        -d '{"useExisting": true}' | jq '.' > /dev/null 2>&1
    print_status $? "Generate summaries API endpoint"
    
else
    echo -e "${YELLOW}⚠️  API server not running on localhost:8000${NC}"
    echo "To test API endpoints, start the server with: php -S localhost:8000 -t public/"
fi

# Test 4: Check environment variables
echo -e "\n${YELLOW}Test 4: Environment variables check${NC}"
if [ -n "$X_API_KEY" ] && [ -n "$X_API_SECRET" ] && [ -n "$X_TOKEN" ] && [ -n "$X_TOKEN_SECRET" ]; then
    print_status 0 "Twitter API credentials are set"
else
    print_status 1 "Twitter API credentials are missing"
    echo "Required environment variables:"
    echo "  - X_API_KEY"
    echo "  - X_API_SECRET" 
    echo "  - X_TOKEN"
    echo "  - X_TOKEN_SECRET"
fi

# Test 5: Check if required services exist
echo -e "\n${YELLOW}Test 5: Service availability check${NC}"
if [ -f "src/Service/TwitterNewsService.php" ]; then
    print_status 0 "TwitterNewsService exists"
else
    print_status 1 "TwitterNewsService missing"
fi

if [ -f "src/Command/PostTwitterNewsCommand.php" ]; then
    print_status 0 "PostTwitterNewsCommand exists"
else
    print_status 1 "PostTwitterNewsCommand missing"
fi

if [ -f "src/Controller/TwitterNewsController.php" ]; then
    print_status 0 "TwitterNewsController exists"
else
    print_status 1 "TwitterNewsController missing"
fi

# Test 6: Command help
echo -e "\n${YELLOW}Test 6: Command help${NC}"
php bin/console app:post-twitter-news --help > /dev/null 2>&1
print_status $? "Command help display"

echo -e "\n${GREEN}🎉 Testing completed!${NC}"
echo -e "\n${YELLOW}Next steps:${NC}"
echo "1. Set up Twitter API credentials in your environment"
echo "2. Run: php bin/console app:post-twitter-news --dry-run"
echo "3. Test API endpoints if server is running"
echo "4. When ready, run: php bin/console app:post-twitter-news" 