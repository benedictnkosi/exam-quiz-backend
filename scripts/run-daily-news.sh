#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted) nah

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

"$CONSOLE" app:sabc-past-four-hours-direct --no-burn --avatar-id=93bf36d167184854bdde4ffb3b340981 --voice-id=6e032ca64a1745a0b48036e64042b2fb --upload-youtube
