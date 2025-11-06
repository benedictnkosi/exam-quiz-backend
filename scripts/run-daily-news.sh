#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted) nah

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

"$CONSOLE" app:sabc-past-four-hours --avatar-id=93bf36d167184854bdde4ffb3b340981 --voice-id=QOdz6iaNL4YniX0zO8BV --upload-all
