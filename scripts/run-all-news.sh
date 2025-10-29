#!/bin/bash

# Run all news generators: Prime News, Madlanga Commission, Parliamentary ad hoc
#
# Usage examples:
#   ./run-all-news.sh
#   ./run-all-news.sh --days-prime 0 --combine-ewn
#   ./run-all-news.sh --days-madlanga 3 --avatar-id AVATAR --voice-id VOICE
#   ./run-all-news.sh --days-adhoc 1
#
# Options:
#   --days-prime N       Days back for Prime News search (default 0)
#   --combine-ewn        Combine SABC Prime with EWN "The day that was"
#   --days-madlanga N    Days back for Madlanga search (default 7)
#   --days-adhoc N       Days back for Parliamentary ad hoc search (default 7)
#   --avatar-id ID       HeyGen avatar ID for EWN commands (optional)
#   --voice-id ID        HeyGen voice ID for EWN commands (optional)
#   --html-file PATH     Use saved HTML for Prime News (optional)
#   -h, --help           Show help

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

# Defaults
DAYS_PRIME=0
COMBINE_EWN=false
DAYS_MADLANGA=7
DAYS_ADHOC=7
AVATAR_ID=""
VOICE_ID=""
HTML_FILE=""

print_help() {
  cat <<EOF
Run Prime News, Madlanga Commission, and Parliamentary ad hoc generators.

Usage: $0 [options]

Options:
  --days-prime N       Days back for Prime News search (default 0)
  --combine-ewn        Combine SABC Prime with EWN "The day that was"
  --days-madlanga N    Days back for Madlanga search (default 7)
  --days-adhoc N       Days back for Parliamentary ad hoc search (default 7)
  --avatar-id ID       HeyGen avatar ID for EWN commands (optional)
  --voice-id ID        HeyGen voice ID for EWN commands (optional)
  --html-file PATH     Use saved HTML for Prime News (optional)
  -h, --help           Show this help
EOF
}

# Parse args
while [[ $# -gt 0 ]]; do
  case "$1" in
    --days-prime)
      DAYS_PRIME="$2"; shift 2 ;;
    --combine-ewn)
      COMBINE_EWN=true; shift ;;
    --days-madlanga)
      DAYS_MADLANGA="$2"; shift 2 ;;
    --days-adhoc)
      DAYS_ADHOC="$2"; shift 2 ;;
    --avatar-id)
      AVATAR_ID="$2"; shift 2 ;;
    --voice-id)
      VOICE_ID="$2"; shift 2 ;;
    --html-file)
      HTML_FILE="$2"; shift 2 ;;
    -h|--help)
      print_help; exit 0 ;;
    *)
      echo "Unknown option: $1" >&2
      print_help
      exit 1 ;;
  esac
done

# Build optional flags
PRIME_FLAGS=("--days" "$DAYS_PRIME")
if [[ -n "$HTML_FILE" ]]; then
  PRIME_FLAGS+=("--html-file" "$HTML_FILE")
fi
if [[ "$COMBINE_EWN" == "true" ]]; then
  PRIME_FLAGS+=("--combine-ewn")
fi

EWN_COMMON_FLAGS=()
if [[ -n "$AVATAR_ID" ]]; then
  EWN_COMMON_FLAGS+=("--avatar-id" "$AVATAR_ID")
fi
if [[ -n "$VOICE_ID" ]]; then
  EWN_COMMON_FLAGS+=("--voice-id" "$VOICE_ID")
fi

# Pretty printing
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

run_step() {
  local title="$1"; shift
  echo -e "${YELLOW}==> ${title}${NC}"
  if "$@"; then
    echo -e "${GREEN}✔ ${title} completed${NC}\n"
  else
    local code=$?
    echo -e "${RED}✖ ${title} failed (exit $code)${NC}\n"
    return $code
  fi
}

OVERALL_RC=0

# 1) Prime News
run_step "Prime News (SABC Digital)" \
  "$CONSOLE" app:sabcdigital:scrape-prime-news "${PRIME_FLAGS[@]}" || OVERALL_RC=$?

# 2) EWN Madlanga
MADLANGA_FLAGS=("--days" "$DAYS_MADLANGA")
MADLANGA_FLAGS+=("${EWN_COMMON_FLAGS[@]}")
run_step "EWN Madlanga Commission" \
  "$CONSOLE" app:ewn-madlanga-video "${MADLANGA_FLAGS[@]}" || OVERALL_RC=$?

# 3) EWN Parliamentary ad hoc
ADHOC_FLAGS=("--days" "$DAYS_ADHOC")
ADHOC_FLAGS+=("${EWN_COMMON_FLAGS[@]}")
run_step "EWN Parliamentary ad hoc" \
  "$CONSOLE" app:ewn-parliamentary-video "${ADHOC_FLAGS[@]}" || OVERALL_RC=$?

exit $OVERALL_RC
