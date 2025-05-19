#!/bin/bash

echo "Starting streak notifications script"
# Get the directory where the script is located
PROJECT_DIR="/var/www/exam-quiz-backend"

# Change to the project directory
cd "$PROJECT_DIR" || exit

# Get the grade parameter if provided
GRADE_ID=$1

# Execute the streak notification sending command with grade parameter if provided
if [ -n "$GRADE_ID" ]; then
    php bin/console app:send-streak-notifications "$GRADE_ID"
else
    php bin/console app:send-streak-notifications
fi

# Log the execution
echo "$(date '+%Y-%m-%d %H:%M:%S') - Streak notifications sent" >> var/log/streak_notifications.log 