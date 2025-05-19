#!/bin/bash

# Get the directory where the script is located
echo "Starting quiz weekend notification script"
PROJECT_DIR="/var/www/exam-quiz-backend"

# Change to the project directory
cd "$PROJECT_DIR" || exit

# Check if grade ID is provided
if [ -z "$1" ]; then
    echo "Error: Grade ID is required"
    echo "Usage: $0 <grade_id>"
    exit 1
fi

GRADE_ID=$1

# Execute the quiz weekend notification command
php bin/console app:send-quiz-weekend-notification "$GRADE_ID"

# Log the execution
echo "$(date '+%Y-%m-%d %H:%M:%S') - Quiz weekend notifications sent for grade $GRADE_ID" >> var/log/quiz_weekend_notifications.log 