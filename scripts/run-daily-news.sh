#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted) nah

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

"$CONSOLE" app:sabc-past-four-hours-direct --no-burn --avatar-id=Rasmus_sitting_lounge_front --voice-id=d2f4f24783d04e22ab49ee8fdc3715e0 --upload-youtube
