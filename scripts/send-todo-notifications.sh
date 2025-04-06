#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Set up logging
LOG_FILE="/var/log/todo-notifications.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Run the command and log the output
echo "[$TIMESTAMP] Starting todo notifications..." >> "$LOG_FILE"
cd "$PROJECT_DIR" && php bin/console app:send-todo-notifications >> "$LOG_FILE" 2>&1
EXIT_CODE=$?

# Log the result
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$TIMESTAMP] Todo notifications completed successfully" >> "$LOG_FILE"
else
    echo "[$TIMESTAMP] Todo notifications failed with exit code $EXIT_CODE" >> "$LOG_FILE"
fi

exit $EXIT_CODE 