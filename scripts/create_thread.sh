#!/bin/bash

SCRIPT_DIR="/var/www/exam-quiz-backend"

# Change to the project root directory (one level up from scripts)
cd "$SCRIPT_DIR"

# Set error handling
set -e

# Print start message
echo "Starting thread creation process..."

# Run the command
php bin/console app:create-thread-from-book

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Thread creation completed successfully"
else
    echo "Thread creation failed"
    exit 1
fi 