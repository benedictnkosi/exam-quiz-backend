#!/bin/bash

# Script to disable inactive commute accounts
# This script should be run via cron job

# Set the application directory
APP_DIR="/path/to/your/app"
cd "$APP_DIR" || exit 1

# Log file
LOG_FILE="var/log/disable_inactive_accounts.log"

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Start the script
log_message "Starting disable inactive commute accounts script"

# Run the command
# You can modify the options as needed:
# --dry-run: Run without making changes (for testing)
# --hours: Number of hours to consider as inactive (default: 48)
# --limit: Maximum number of accounts to process (default: 100)

php bin/console app:disable-inactive-commute-accounts \
    --hours=48 \
    --limit=100 \
    2>&1 | tee -a "$LOG_FILE"

# Check the exit code
if [ $? -eq 0 ]; then
    log_message "Script completed successfully"
else
    log_message "Script failed with exit code $?"
fi

log_message "Script finished" 