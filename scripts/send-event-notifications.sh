#!/bin/bash

# Set absolute paths
PROJECT_DIR="/var/www/exam-quiz-backend"

# Debug information
echo "Current directory: $(pwd)"
echo "Project directory: $PROJECT_DIR"
echo "PHP path: $(which php)"

# Set up timestamp
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Run the command
echo "[$TIMESTAMP] Starting event notifications..."
cd "$PROJECT_DIR" && /usr/bin/php bin/console app:send-event-notifications
EXIT_CODE=$?

# Log the result
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$TIMESTAMP] Event notifications completed successfully"
else
    echo "[$TIMESTAMP] Event notifications failed with exit code $EXIT_CODE"
fi

exit $EXIT_CODE 