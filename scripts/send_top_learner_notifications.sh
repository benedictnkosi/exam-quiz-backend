#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Change to the project directory
cd "$PROJECT_DIR"

# Run the daily top learner notifications command
php bin/console app:send-top-learner-notifications

# Run the weekly top learner notifications command (only on Mondays)
if [ "$(date +%u)" = "1" ]; then
    php bin/console app:send-last-week-top-learner-notifications
fi 