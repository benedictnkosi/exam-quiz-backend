#!/bin/bash

# Test script: burn caption.ass onto the specified video using ffmpeg

set -euo pipefail

ROOT_DIR="$(dirname "$0")/.."
VIDEO_IN="$ROOT_DIR/1af116fb996349529cff0c6ae2968610.mp4"
SUBS_IN="$ROOT_DIR/caption.ass"
VIDEO_OUT="$ROOT_DIR/burned_test.mp4"

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg not found. Please install ffmpeg." >&2
  exit 1
fi

if [ ! -f "$VIDEO_IN" ]; then
  echo "Input video not found: $VIDEO_IN" >&2
  exit 1
fi

if [ ! -f "$SUBS_IN" ]; then
  echo "Subtitle file not found: $SUBS_IN" >&2
  exit 1
fi

echo "Burning captions: $SUBS_IN -> $VIDEO_IN"
# Escape any ':' in subtitle path for the subtitles filter
ESC_SUBS="${SUBS_IN//:/\\:}"

ffmpeg -y -loglevel info \
  -i "$VIDEO_IN" \
  -vf "subtitles='${ESC_SUBS}':force_style='Alignment=5,MarginV=150,MarginL=40,MarginR=5,Outline=1,FontSize=20,LineSpacing=2,WrapStyle=0'" \
  -c:a copy \
  "$VIDEO_OUT"

echo "Done. Output: $VIDEO_OUT"


