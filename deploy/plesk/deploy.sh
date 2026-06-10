#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="${1:-$SCRIPT_DIR/live.env}"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  source "$ENV_FILE"
  set +a
fi

PHP_BIN="${PHP_BIN:-php}"

cd "$PROJECT_ROOT"

echo "Pulling latest code..."
git pull --ff-only

echo "Checking PHP syntax..."
find admin api -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >/tmp/max-app-php-lint.log
cat /tmp/max-app-php-lint.log

if [[ -x bot/.venv/bin/python ]]; then
  echo "Updating bot dependencies..."
  bot/.venv/bin/pip install -r bot/requirements.txt
  echo "Checking Python syntax..."
  bot/.venv/bin/python - <<'PY'
import ast
from pathlib import Path
files = list(Path("bot").rglob("*.py"))
for path in files:
    ast.parse(path.read_text(encoding="utf-8"), filename=str(path))
print(f"Python syntax OK: {len(files)} files")
PY
fi

if command -v systemctl >/dev/null 2>&1; then
  if systemctl list-unit-files | grep -q '^max-app-telegram.service'; then
    echo "Restarting Telegram bot service..."
    sudo systemctl restart max-app-telegram.service
  fi
fi

echo "Deploy complete."
