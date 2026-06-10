#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

cd "$PROJECT_ROOT"

if [[ ! -x bot/.venv/bin/python ]]; then
  echo "Missing bot virtualenv: bot/.venv"
  echo "Run: bash deploy/plesk/install.sh deploy/plesk/live.env"
  exit 1
fi

if [[ ! -f bot/.env ]]; then
  echo "Missing bot/.env"
  exit 1
fi

echo "Running Telegram diagnostics..."
bot/.venv/bin/python -m bot.telegram.diagnostics

if command -v systemctl >/dev/null 2>&1; then
  echo
  echo "Service status:"
  systemctl status max-app-telegram.service --no-pager || true

  echo
  echo "Recent logs:"
  journalctl -u max-app-telegram.service -n 80 --no-pager || true
fi
