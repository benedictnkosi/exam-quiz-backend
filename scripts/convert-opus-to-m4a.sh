#!/bin/bash

# Set the output bitrate (optional)
BITRATE="128k"

# Create an output folder (optional)
OUTPUT_DIR="converted_m4a"
mkdir -p "$OUTPUT_DIR"

echo "Starting batch conversion of .opus files to .m4a..."

# Loop through all .opus files
for f in *.opus; do
  # Skip if no .opus files found
  [ -e "$f" ] || continue

  # Output filename
  output_file="$OUTPUT_DIR/${f%.opus}.m4a"

  # Skip if output file already exists
  if [ -f "$output_file" ]; then
    echo "Skipping $f - already converted to $output_file"
    continue
  fi

  echo "Converting: $f -> $output_file"

  # Convert using ffmpeg
  ffmpeg -i "$f" -c:a aac -b:a "$BITRATE" "$output_file"
done

echo "Conversion completed!"
echo "Converted files are in the '$OUTPUT_DIR' directory" 