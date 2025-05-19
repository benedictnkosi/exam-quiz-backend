#!/bin/bash

# Set the script to exit on any error
set -e

TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Change to the project root directory
cd /var/www/exam-quiz-backend

# Check if the command exists
if ! command -v php &> /dev/null; then
    echo "[$TIMESTAMP] Error: PHP is not installed or not in PATH"
    exit 1
fi

# Check if the Symfony console exists
if [ ! -f "bin/console" ]; then
    echo "[$TIMESTAMP] Error: Symfony console not found at bin/console"
    exit 1
fi

# Run the command
echo "[$TIMESTAMP] Starting reset inactive capturers command..."
if php bin/console app:reset-inactive-capturers; then
    echo "[$TIMESTAMP] Command completed successfully"
    exit 0
else
    echo "[$TIMESTAMP] Command failed with exit code $?"
    exit 1
fi 