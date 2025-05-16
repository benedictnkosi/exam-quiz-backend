#!/bin/bash

# Change to the correct directory
cd /var/www/exam-quiz-backend

# Set error handling
set -e

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Start the script
log_message "Starting question creation process..."

# Run the regular questions command
log_message "Running regular questions command..."
php bin/console app:create-questions

# Check if the regular questions command was successful
if [ $? -eq 0 ]; then
    log_message "Regular questions creation process completed successfully"
else
    log_message "Error: Regular questions creation process failed"
    exit 1
fi

# Run the math questions command
log_message "Running math questions command..."
php bin/console app:create-math-questions

# Check if the math questions command was successful
if [ $? -eq 0 ]; then
    log_message "Math questions creation process completed successfully"
else
    log_message "Error: Math questions creation process failed"
    exit 1
fi

log_message "All question creation processes completed successfully" 