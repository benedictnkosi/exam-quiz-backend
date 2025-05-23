#!/bin/bash

# Script to generate chapters for new story arcs
# This script runs the chapter generation process for new story arcs

SCRIPT_DIR="/var/www/exam-quiz-backend"

# Change to the project root directory (one level up from scripts)
cd "$SCRIPT_DIR"

# Load environment variables if .env file exists
if [ -f .env ]; then
    source .env
fi

# Set script to exit on error
set -e

echo "Starting chapter generation process..."

# Run the chapter generation command
php bin/console app:generate-chapters

echo "Chapter generation completed successfully!" 