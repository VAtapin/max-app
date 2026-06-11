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

if [[ -f "$ENV_FILE" ]]; then
  php_quote() {
    local value="${1:-}"
    value="${value//\\/\\\\}"
    value="${value//\'/\\\'}"
    printf "'%s'" "$value"
  }

  echo "Updating PHP local config..."
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
        'mini_app_url' => $(php_quote "${SWPRO_MINI_APP_URL:-https://swpro.ru/mini-app/index.html}"),
        'vk_app_id' => $(php_quote "${VK_APP_ID:-}"),
        'vk_secure_key' => $(php_quote "${VK_SECURE_KEY:-}"),
        'vk_service_token' => $(php_quote "${VK_SERVICE_TOKEN:-}"),
    ],
];
PHP
fi

if [[ -f "$ENV_FILE" ]]; then
  echo "Applying database migrations..."
  bash deploy/plesk/migrate-db.sh "$ENV_FILE"
fi

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
