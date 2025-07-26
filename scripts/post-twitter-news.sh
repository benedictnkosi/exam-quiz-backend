#!/bin/bash

# Script to run the Symfony PostTwitterNewsCommand
# Usage: ./post-twitter-news.sh [--dry-run] [--use-existing] [--single] [scope]
# Example: ./post-twitter-news.sh --dry-run global

# Path to Symfony console
CONSOLE="$(dirname "$0")/../bin/console"

# Default command
CMD="app:post-twitter-news"

# Forward all arguments to the Symfony command
$CONSOLE $CMD "$@" 