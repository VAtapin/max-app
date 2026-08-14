#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="${1:-$SCRIPT_DIR/live.env}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing application configuration: $ENV_FILE"
  exit 1
fi

set -a
source "$ENV_FILE"
set +a

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${SWPRO_PUBLIC_URL:?SWPRO_PUBLIC_URL is required}"
: "${SWPRO_MINI_APP_URL:?SWPRO_MINI_APP_URL is required}"

PHP_BIN="${PHP_BIN:-php}"

cd "$PROJECT_ROOT"

echo "Pulling latest code..."
git pull --ff-only

echo "Using the single application configuration: $ENV_FILE"
rm -f admin/app/config/local.php bot/.env

mkdir -p admin/uploads/products admin/uploads/content admin/uploads/tests admin/uploads/profiles admin/uploads/broadcasts admin/uploads/files admin/uploads/responses admin/uploads/payments storage/logs

echo "Applying database migrations..."
bash deploy/plesk/migrate-db.sh "$ENV_FILE"
echo "Synchronizing Docsify with the AI knowledge index..."
SWPRO_ENV_FILE="$ENV_FILE" "$PHP_BIN" admin/cron/sync-docsify-ai.php
SWPRO_ENV_FILE="$ENV_FILE" "$PHP_BIN" admin/cron/run-billing.php
echo "Checking the configured PHP database..."
SWPRO_ENV_FILE="$ENV_FILE" "$PHP_BIN" -r \
  'require "admin/app/core/db.php"; echo "Database: ", db()->query("SELECT DATABASE()")->fetchColumn(), PHP_EOL;'

echo "Checking PHP syntax..."
find admin api -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >/tmp/max-app-php-lint.log
find . -maxdepth 1 -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >>/tmp/max-app-php-lint.log
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
    if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
      echo "Restarting Telegram bot service..."
      systemctl restart max-app-telegram.service
    elif command -v sudo >/dev/null 2>&1 && sudo -n true >/dev/null 2>&1; then
      echo "Restarting Telegram bot service..."
      sudo systemctl restart max-app-telegram.service
    else
      echo "Skipping Telegram bot restart: run systemctl restart max-app-telegram.service as root if bot code changed."
    fi
  fi
fi

echo "Deploy complete."
