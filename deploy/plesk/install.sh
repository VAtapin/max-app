#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="${1:-$SCRIPT_DIR/live.env}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing env file: $ENV_FILE"
  echo "Copy deploy/plesk/env.example to deploy/plesk/live.env and fill it first."
  exit 1
fi

set -a
source "$ENV_FILE"
set +a

: "${APP_DOMAIN:?APP_DOMAIN is required}"
: "${APP_ROOT:?APP_ROOT is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

PHP_BIN="${PHP_BIN:-php}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

cd "$PROJECT_ROOT"

php_quote() {
  local value="${1:-}"
  value="${value//\\/\\\\}"
  value="${value//\'/\\\'}"
  printf "'%s'" "$value"
}

echo "Checking PHP..."
"$PHP_BIN" -v
"$PHP_BIN" -m | grep -qi '^pdo_mysql$' || {
  echo "PHP extension pdo_mysql is required."
  exit 1
}

echo "Writing PHP local config..."
cat > admin/app/config/local.php <<PHP
<?php

return [
    'app' => [
        'base_url' => '/admin/public',
    ],
    'db' => [
        'host' => $(php_quote "$DB_HOST"),
        'port' => $(php_quote "$DB_PORT"),
        'database' => $(php_quote "$DB_DATABASE"),
        'username' => $(php_quote "$DB_USERNAME"),
        'password' => $(php_quote "$DB_PASSWORD"),
        'charset' => 'utf8mb4',
    ],
    'integrations' => [
        'telegram_bot_token' => $(php_quote "${TELEGRAM_BOT_TOKEN:-}"),
        'vk_app_id' => $(php_quote "${VK_APP_ID:-}"),
        'vk_secure_key' => $(php_quote "${VK_SECURE_KEY:-}"),
        'vk_service_token' => $(php_quote "${VK_SERVICE_TOKEN:-}"),
    ],
];
PHP

echo "Writing bot environment..."
cat > bot/.env <<BOTENV
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_NAME=${DB_DATABASE}
DB_USER=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN:-}
MAX_BOT_TOKEN=${MAX_BOT_TOKEN:-}
MAX_API_BASE_URL=${MAX_API_BASE_URL:-https://botapi.max.ru}
SWPRO_MINI_APP_URL=${SWPRO_MINI_APP_URL:-https://swpro.ru/mini-app/index.html}
LOG_LEVEL=${LOG_LEVEL:-INFO}
BOTENV

echo "Preparing Python virtualenv..."
"$PYTHON_BIN" -m venv bot/.venv
bot/.venv/bin/python -m pip install --upgrade pip
bot/.venv/bin/pip install -r bot/requirements.txt

echo "Creating upload directories..."
mkdir -p admin/uploads/products admin/uploads/content admin/uploads/broadcasts admin/uploads/files admin/uploads/responses

if [[ -d uploads ]]; then
  echo "Recovering misplaced uploaded files from ./uploads to ./admin/uploads..."
  cp -nR uploads/. admin/uploads/ || true
fi

if [[ -n "${APP_USER:-}" ]]; then
  echo "Applying ownership to $APP_USER:${APP_GROUP:-psacln}..."
  chown -R "$APP_USER:${APP_GROUP:-psacln}" admin/uploads admin/app/config/local.php bot/.env bot/.venv || true
fi

echo "Checking PHP syntax..."
find admin api -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >/tmp/max-app-php-lint.log
cat /tmp/max-app-php-lint.log

echo "Checking Python syntax..."
bot/.venv/bin/python - <<'PY'
import ast
from pathlib import Path
files = list(Path("bot").rglob("*.py"))
for path in files:
    ast.parse(path.read_text(encoding="utf-8"), filename=str(path))
print(f"Python syntax OK: {len(files)} files")
PY

echo "Install files prepared."
echo "Next:"
echo "  1) Import DB: deploy/plesk/import-db.sh $ENV_FILE"
echo "  2) Install systemd bot service if needed: see deploy/plesk/max-app-telegram.service"
echo "  3) Open https://${APP_DOMAIN}/api/index.php"
