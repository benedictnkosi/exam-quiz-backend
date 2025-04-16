#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Change to the project directory
cd "$PROJECT_DIR" || exit

# Execute the streak notification sending command
php bin/console app:send-streak-notifications

# Log the execution
echo "$(date '+%Y-%m-%d %H:%M:%S') - Streak notifications sent" >> var/log/streak_notifications.log 