#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Change to the project root directory
cd "$PROJECT_ROOT"

# Run the Symfony command
php bin/console app:delete-all-files

# Check if the command was successful
if [ $? -eq 0 ]; then
    echo "Files deleted successfully!"
else
    echo "Failed to delete files."
    exit 1
fi 