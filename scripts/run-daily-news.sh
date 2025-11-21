#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted) nah

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

"$CONSOLE" app:sabc-past-four-hours-direct --no-burn --avatar-id=11c2dce8c39c4987b810b7502b6820e2 --voice-id=dcc89bc2097f47bd93f0c9e8d5e53b5f --upload-youtube
