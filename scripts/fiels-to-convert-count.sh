#!/bin/bash

# Set the output bitrate (optional)
BITRATE="128k"

# Create an output folder (optional)
OUTPUT_DIR="converted_m4a"

echo "Starting batch conversion of .opus files to .m4a..."

# Count files that need to be converted
total_to_convert=0
skipped_count=0

# First pass to count files that need conversion
for f in *.opus; do
  [ -e "$f" ] || continue
  output_file="$OUTPUT_DIR/${f%.opus}.m4a"
  if [ -f "$output_file" ]; then
    skipped_count=$((skipped_count + 1))
  else
    total_to_convert=$((total_to_convert + 1))
  fi
done

if [ "$total_to_convert" -eq 0 ]; then
    echo "No files need conversion. All $skipped_count files are already converted."
    exit 0
fi

echo "Found $total_to_convert files to convert ($skipped_count already converted)"
