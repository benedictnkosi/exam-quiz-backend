#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted)

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

"$CONSOLE" app:sabcdigital:scrape-prime-news
