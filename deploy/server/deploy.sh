#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="/var/www/swpro"
ENV_FILE="${1:-$PROJECT_ROOT/deploy/plesk/live.env}"
PHP_BIN="/usr/bin/php8.3"
PYTHON_BIN="$PROJECT_ROOT/.venv/bin/python"
PIP_BIN="$PROJECT_ROOT/.venv/bin/pip"
APP_USER="swpro"

if [[ ! -d "$PROJECT_ROOT/.git" ]]; then
  echo "Git repository not found: $PROJECT_ROOT"
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing application configuration: $ENV_FILE"
  exit 1
fi

if [[ ! -x "$PHP_BIN" ]]; then
  echo "PHP binary not found: $PHP_BIN"
  exit 1
fi

if [[ ! -x "$PYTHON_BIN" || ! -x "$PIP_BIN" ]]; then
  echo "Python virtual environment not found: $PROJECT_ROOT/.venv"
  exit 1
fi

# Run application-level deployment work as the project owner.
if [[ "${EUID}" -eq 0 && "${SWPRO_DEPLOY_AS_USER:-0}" != "1" ]]; then
  runuser -u "$APP_USER" -- env SWPRO_DEPLOY_AS_USER=1 "$0" "$@"

  # Restart the Telegram bot only if it is already running.
  # This avoids accidentally starting the new server before cutover.
  if systemctl is-active --quiet max-app-telegram.service; then
    echo "Restarting Telegram bot service..."
    systemctl restart max-app-telegram.service
  else
    echo "Telegram bot is inactive; leaving it stopped."
  fi

  if [[ -x /usr/local/sbin/swpro-health.sh ]]; then
    echo "Running server health check..."
    /usr/local/sbin/swpro-health.sh
  fi

  echo "Deploy complete."
  exit 0
fi

cd "$PROJECT_ROOT"

# The server checkout must already have been updated by the deploy wrapper.
if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
  echo "Tracked local changes detected. Refusing to deploy."
  git status --short
  exit 1
fi

echo "Loading application configuration..."
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

# Keep one source of runtime configuration.
rm -f admin/app/config/local.php bot/.env

mkdir -p \
  admin/uploads/products \
  admin/uploads/content \
  admin/uploads/tests \
  admin/uploads/profiles \
  admin/uploads/broadcasts \
  admin/uploads/files \
  admin/uploads/responses \
  admin/uploads/payments \
  storage/logs

echo "Applying database migrations..."
bash deploy/plesk/migrate-db.sh "$ENV_FILE"

echo "Checking database connection..."
SWPRO_ENV_FILE="$ENV_FILE" "$PHP_BIN" -r \
  'require "admin/app/core/db.php"; echo "Database: ", db()->query("SELECT DATABASE()")->fetchColumn(), PHP_EOL;'

echo "Synchronizing Docsify with the AI knowledge index..."
SWPRO_ENV_FILE="$ENV_FILE" "$PHP_BIN" admin/cron/sync-docsify-ai.php

echo "Updating Telegram bot dependencies..."
"$PIP_BIN" install -r bot/requirements.txt

echo "Checking Python syntax..."
"$PYTHON_BIN" - <<'PY'
import ast
from pathlib import Path

files = list(Path("bot").rglob("*.py"))
for path in files:
    ast.parse(path.read_text(encoding="utf-8"), filename=str(path))
print(f"Python syntax OK: {len(files)} files")
PY

echo "Checking PHP syntax..."
find admin api -name '*.php' -print0 \
  | xargs -0 -n1 "$PHP_BIN" -l >/tmp/swpro-php-lint.log
find . -maxdepth 1 -name '*.php' -print0 \
  | xargs -0 -n1 "$PHP_BIN" -l >>/tmp/swpro-php-lint.log
cat /tmp/swpro-php-lint.log

echo "Application deploy finished successfully."
