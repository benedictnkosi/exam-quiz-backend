#!/bin/bash

# Get the directory where the script is located

PROJECT_ROOT="/var/www/exam-quiz-backend"

# Change to the project root directory
cd "$PROJECT_ROOT" || exit

# Run the command
php bin/console app:send-daily-achievement-notifications

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Daily achievement notifications sent successfully"
else
    echo "Failed to send daily achievement notifications"
    exit 1
fi 