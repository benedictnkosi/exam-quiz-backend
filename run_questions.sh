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

# Run the command
php bin/console app:create-questions 2>&1 | tee -a "$LOG_FILE"

# Check if the command was successful
if [ $? -eq 0 ]; then
    log_message "Question creation process completed successfully"
else
    log_message "Error: Question creation process failed"
    exit 1
fi 