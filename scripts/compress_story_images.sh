#!/bin/bash

# Script to compress and resize all images in story-images directory
# Resizes to 512x512 pixels and compresses with 90% quality

# Set the source directory
SOURCE_DIR="public/assets/story-images"
OUTPUT_DIR="public/assets/compressed-story-images-2"

# Check if ImageMagick is installed
if ! command -v convert &> /dev/null; then
    echo "Error: ImageMagick is not installed. Please install it first."
    echo "On macOS: brew install imagemagick"
    echo "On Ubuntu/Debian: sudo apt-get install imagemagick"
    exit 1
fi

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

# Counter for processed files
processed=0
total=0

# Count total PNG files
for file in "$SOURCE_DIR"/*.png; do
    if [[ -f "$file" ]]; then
        ((total++))
    fi
done

echo "Found $total PNG files to process"
echo "Resizing to 512x512 pixels with 90% compression quality"
echo "Output directory: $OUTPUT_DIR"
echo ""

# Process each PNG file
for file in "$SOURCE_DIR"/*.png; do
    if [[ -f "$file" ]]; then
        filename=$(basename "$file")
        output_file="$OUTPUT_DIR/$filename"
        
        echo "Processing: $filename"
        
        # Resize to 512x512 and compress with 90% quality
        convert "$file" -resize 256x256^ -gravity center -extent 256x256 -quality 90 "$output_file"
        
        if [[ $? -eq 0 ]]; then
            ((processed++))
            
            # Get original and new file sizes
            original_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
            new_size=$(stat -f%z "$output_file" 2>/dev/null || stat -c%s "$output_file" 2>/dev/null)
            
            # Calculate compression ratio
            if [[ $original_size -gt 0 ]]; then
                compression_ratio=$(echo "scale=1; (1 - $new_size / $original_size) * 100" | bc -l 2>/dev/null || echo "N/A")
                echo "  ✓ Compressed: ${original_size} bytes → ${new_size} bytes (${compression_ratio}% reduction)"
            else
                echo "  ✓ Processed successfully"
            fi
        else
            echo "  ✗ Error processing $filename"
        fi
        
        echo ""
    fi
done

echo "=== Summary ==="
echo "Total files processed: $processed/$total"
echo "Compressed images saved to: $OUTPUT_DIR"
echo ""
echo "Note: Original files remain unchanged. Compressed versions are in the 'compressed' subdirectory."
