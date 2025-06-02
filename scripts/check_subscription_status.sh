#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Log file setup
LOG_DIR="$PROJECT_ROOT/var/log"
LOG_FILE="$LOG_DIR/subscription_check.log"

# Ensure log directory exists
mkdir -p "$LOG_DIR"

# Log start time
echo "Starting subscription status check at $(date)" >> "$LOG_FILE"

# Run the command and capture output
cd "$PROJECT_ROOT" || exit
php bin/console app:check-subscriptions >> "$LOG_FILE" 2>&1

# Log completion
echo "Completed subscription status check at $(date)" >> "$LOG_FILE"
echo "----------------------------------------" >> "$LOG_FILE" 