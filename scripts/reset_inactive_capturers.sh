#!/bin/bash

# Set the script to exit on any error
set -e

# Define log file
LOG_FILE="var/log/reset_capturers.log"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Create log directory if it doesn't exist
mkdir -p var/log

# Function to log messages
log_message() {
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

# Change to the project root directory
cd "$(dirname "$0")/.." || exit

# Check if the command exists
if ! command -v php &> /dev/null; then
    log_message "Error: PHP is not installed or not in PATH"
    exit 1
fi

# Check if the Symfony console exists
if [ ! -f "bin/console" ]; then
    log_message "Error: Symfony console not found at bin/console"
    exit 1
fi

# Run the command
log_message "Starting reset inactive capturers command..."
if php bin/console app:reset-inactive-capturers; then
    log_message "Command completed successfully"
    exit 0
else
    log_message "Command failed with exit code $?"
    exit 1
fi 