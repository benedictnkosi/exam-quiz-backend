#!/bin/bash

# Set absolute paths
PROJECT_DIR="/var/www/exam-quiz-backend"
LOG_FILE="/var/log/todo-notifications.log"

# Debug information
echo "Current directory: $(pwd)"
echo "Project directory: $PROJECT_DIR"
echo "PHP path: $(which php)"

# Set up logging
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Run the command and log the output
echo "[$TIMESTAMP] Starting todo notifications..." >> "$LOG_FILE"
cd "$PROJECT_DIR" && /usr/bin/php bin/console app:send-todo-notifications >> "$LOG_FILE" 2>&1
EXIT_CODE=$?

# Log the result
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$TIMESTAMP] Todo notifications completed successfully" >> "$LOG_FILE"
else
    echo "[$TIMESTAMP] Todo notifications failed with exit code $EXIT_CODE" >> "$LOG_FILE"
fi

exit $EXIT_CODE 