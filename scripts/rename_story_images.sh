#!/bin/bash

# Remove timestamp from image filenames in public/assets/story-images/
# Example: plot_6_ch1_img1_1751312310.png -> plot_6_ch1_img1.png

cd "$(dirname "$0")/../public/assets/compressed-story-images" || exit 1

for file in plot_*_ch*_img*_[0-9]*.png; do
  # Extract base name without timestamp
  base="${file%_*}"
  ext="${file##*.}"
  newname="${base}.${ext}"
  if [[ "$file" != "$newname" ]]; then
    mv "$file" "$newname"
    echo "Renamed $file -> $newname"
  fi
done 