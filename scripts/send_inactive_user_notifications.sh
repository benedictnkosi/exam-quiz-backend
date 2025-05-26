#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Change to the project root directory
cd "$PROJECT_ROOT" || exit

# Run the command
php bin/console app:send-inactive-user-notifications

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Inactive user notifications sent successfully"
else
    echo "Failed to send inactive user notifications"
    exit 1
fi 