#!/bin/bash

# Script to populate threads for grades 1-3 for term 2
echo "Starting thread population for grades 1-3..."

# Grade 1
echo "Populating threads for Grade 1..."
curl -X GET "https://examquiz.co.za/api/threads/populate?grade=1&term=2"
echo -e "\n"

# Grade 2
echo "Populating threads for Grade 2..."
curl -X GET "https://examquiz.co.za/api/threads/populate?grade=2&term=2"
echo -e "\n"

# Grade 3
echo "Populating threads for Grade 3..."
curl -X GET "https://examquiz.co.za/api/threads/populate?grade=3&term=2"
echo -e "\n"

echo "Thread population completed!" 