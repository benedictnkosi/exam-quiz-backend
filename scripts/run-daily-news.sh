#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted) nah

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

"$CONSOLE" app:sabc-past-four-hours-direct --no-burn --avatar-id=93bf36d167184854bdde4ffb3b340981 --voice-id=24f17f42c557477faa14c21920ad0713 --upload-youtube
