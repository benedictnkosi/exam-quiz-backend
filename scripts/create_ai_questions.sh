#!/bin/bash

# Set error handling
set -e

# Create logs directory if it doesn't exist
mkdir -p logs

# Get current timestamp
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="logs/questions_${TIMESTAMP}.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Start the script
log_message "Starting question creation process..."

# Run the regular questions command
log_message "Running regular questions command..."
php bin/console app:create-questions 2>&1 | tee -a "$LOG_FILE"

# Check if the regular questions command was successful
if [ $? -eq 0 ]; then
    log_message "Regular questions creation process completed successfully"
else
    log_message "Error: Regular questions creation process failed"
    exit 1
fi

# Run the math questions command
log_message "Running math questions command..."
php bin/console app:create-math-questions 2>&1 | tee -a "$LOG_FILE"

# Check if the math questions command was successful
if [ $? -eq 0 ]; then
    log_message "Math questions creation process completed successfully"
else
    log_message "Error: Math questions creation process failed"
    exit 1
fi

log_message "All question creation processes completed successfully" 