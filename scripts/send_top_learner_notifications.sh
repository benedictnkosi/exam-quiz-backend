#!/bin/bash

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting top learner notifications script"

# Get the directory where the script is located
PROJECT_DIR="/var/www/exam-quiz-backend"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Project directory: $PROJECT_DIR"

# Change to the project directory
cd "$PROJECT_DIR"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Changed to project directory"

# Run the daily top learner notifications command
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running daily top learner notifications..."
php bin/console app:send-top-learner-notifications
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Daily notifications completed"

# Run the weekly top learner notifications command (only on Mondays)
if [ "$(date +%u)" = "1" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] It's Monday - running weekly top learner notifications..."
    php bin/console app:send-last-week-top-learner-notifications
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Weekly notifications completed"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Not Monday - skipping weekly notifications"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Script completed successfully" 