#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Change to the project directory
cd "$PROJECT_DIR" || exit

# Array of grades to process
GRADES=(1 2 3)

# Loop through each grade
for GRADE_ID in "${GRADES[@]}"; do
    echo "================================================"
    echo "Starting content generation process for grade $GRADE_ID..."
    echo "================================================"

    # 1. Generate topics for questions
    echo "Step 1: Generating topics for questions..."
    php bin/console app:generate-question-topics "$GRADE_ID"

    # 2. Generate lectures for topics
    echo "Step 2: Generating lectures for topics..."
    php bin/console app:generate-lecture

    # 3. Record lectures
    echo "Step 3: Recording lectures..."
    php bin/console app:record-lecture

    # Log the execution
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Content generation completed for grade $GRADE_ID" >> var/log/content_generation.log
    
    echo "================================================"
    echo "Completed processing for grade $GRADE_ID"
    echo "================================================"
    echo ""
done

echo "All grades have been processed successfully!" 