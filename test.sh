#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting API Tests...${NC}"


# Run the test command
echo -e "${GREEN}Running API endpoint tests...${NC}"
php bin/console app:test-api-endpoints

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo -e "${GREEN}API Tests completed successfully!${NC}"
else
    echo -e "${RED}API Tests failed!${NC}"
    exit 1
fi 