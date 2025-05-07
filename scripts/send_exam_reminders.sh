#!/bin/bash

# Script to send exam reminders
# This script should be run daily via cron
# Usage: ./send_exam_reminders.sh [days]
# If days is not provided, defaults to 1

# Set the project root directory (adjust this path to match your project structure)
PROJECT_ROOT="/Users/mac1/Documents/cursor/exam_quiz_backend"

# Set the log directory
LOG_DIR="${PROJECT_ROOT}/var/log"
LOG_FILE="${LOG_DIR}/exam_reminders.log"

# Create log directory if it doesn't exist
mkdir -p "${LOG_DIR}"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG_FILE}"
}

# Get number of days from parameter or use default
DAYS=${1:-1}

# Validate days parameter
if ! [[ "$DAYS" =~ ^[0-9]+$ ]]; then
    log_message "ERROR: Days parameter must be a positive number"
    exit 1
fi

# Change to project directory
cd "${PROJECT_ROOT}" || {
    log_message "ERROR: Failed to change to project directory"
    exit 1
}

# Run the command
log_message "Starting exam reminder process for ${DAYS} days"
php bin/console app:send-exam-reminders --days="${DAYS}" >> "${LOG_FILE}" 2>&1

# Check the exit status
if [ $? -eq 0 ]; then
    log_message "Exam reminder process completed successfully"
else
    log_message "ERROR: Exam reminder process failed"
    exit 1
fi

exit 0 