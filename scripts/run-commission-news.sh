#!/bin/bash

# Simplified runner: execute the three news commands as-is (no params accepted)

set -euo pipefail

CONSOLE="$(dirname "$0")/../bin/console"

# Order and arguments match project.md exactly
"$CONSOLE" app:ewn-parliamentary-video \
  --days=0 \
  --avatar-id=d8190501dc7c4b77b852400a2008c984 \
  --voice-id=007e1378fc454a9f976db570ba6164a7

"$CONSOLE" app:ewn-madlanga-video \
  --days=0 \
  --avatar-id=bb645f6e5a1b4407bc002967034f65e8 \
  --voice-id=d41b5163f39044129d06aca88d7a8f4f