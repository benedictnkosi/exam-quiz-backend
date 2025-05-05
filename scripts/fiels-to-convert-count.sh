#!/bin/bash

# Set the output bitrate (optional)
BITRATE="128k"

# Create an output folder (optional)
OUTPUT_DIR="converted_m4a"
mkdir -p "$OUTPUT_DIR"

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

# Initialize counter
converted_count=0

# Loop through all .opus files
for f in *.opus; do
  # Skip if no .opus files found
  [ -e "$f" ] || continue

  # Output filename
  output_file="$OUTPUT_DIR/${f%.opus}.m4a"

  # Skip if output file already exists
  if [ -f "$output_file" ]; then
    continue
  fi

  echo "[$((converted_count + 1))/$total_to_convert] Converting: $f -> $output_file"

  # Convert using ffmpeg
  ffmpeg -i "$f" -c:a aac -b:a "$BITRATE" "$output_file"
  
  if [ $? -eq 0 ]; then
    converted_count=$((converted_count + 1))
  fi
done

echo "Conversion completed!"
echo "Summary:"
echo "- Files converted: $converted_count of $total_to_convert"
echo "- Files already converted: $skipped_count"
echo "Converted files are in the '$OUTPUT_DIR' directory" 