#!/bin/bash

# Script to generate and populate content for the exam quiz system

echo "Starting content generation process..."

echo "Generating question topics..."
php bin/console app:generate-question-topics 1
php bin/console app:generate-question-topics 2
php bin/console app:generate-question-topics 3

echo "Populating topics..."
php bin/console app:populate-topics

echo "Generating lecture..."
php bin/console app:generate-lecture

echo "Recording lecture..."
php bin/console app:record-lecture

echo "Content generation process completed!" 