#!/bin/bash

# Change to the project directory
cd "$(dirname "$0")/.."

# Run the Symfony console command
php bin/console app:send-inactive-learner-notifications

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Inactive learner notifications sent successfully"
else
    echo "Error sending inactive learner notifications"
    exit 1
fi 