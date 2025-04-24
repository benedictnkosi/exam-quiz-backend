#!/bin/bash

# Set absolute paths
PROJECT_DIR="/var/www/exam-quiz-backend"
LOG_FILE="/var/log/event-notifications.log"

# Debug information
echo "Current directory: $(pwd)"
echo "Project directory: $PROJECT_DIR"
echo "PHP path: $(which php)"

# Set up logging
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Run the command and log the output
echo "[$TIMESTAMP] Starting event notifications..." >> "$LOG_FILE"
cd "$PROJECT_DIR" && /usr/bin/php bin/console app:send-event-notifications >> "$LOG_FILE" 2>&1
EXIT_CODE=$?

# Log the result
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$TIMESTAMP] Event notifications completed successfully" >> "$LOG_FILE"
else
    echo "[$TIMESTAMP] Event notifications failed with exit code $EXIT_CODE" >> "$LOG_FILE"
fi

exit $EXIT_CODE 