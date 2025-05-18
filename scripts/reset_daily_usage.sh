#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the project root directory (one level up from scripts)
cd "$SCRIPT_DIR/.."

# Execute the Symfony command
php bin/console app:reset-daily-usage

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Daily usage data has been reset successfully."
else
    echo "Error: Failed to reset daily usage data."
    exit 1
fi 