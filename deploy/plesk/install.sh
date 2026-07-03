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
: "${SWPRO_PUBLIC_URL:?SWPRO_PUBLIC_URL is required}"
: "${SWPRO_MINI_APP_URL:?SWPRO_MINI_APP_URL is required}"

PHP_BIN="${PHP_BIN:-php}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

cd "$PROJECT_ROOT"

echo "Checking PHP..."
"$PHP_BIN" -v
for extension in pdo_mysql mbstring openssl fileinfo; do
  "$PHP_BIN" -m | grep -qi "^${extension}$" || {
    echo "PHP extension ${extension} is required."
    exit 1
  }
done

echo "Using the single application configuration: $ENV_FILE"
rm -f admin/app/config/local.php bot/.env

echo "Preparing Python virtualenv..."
"$PYTHON_BIN" -m venv bot/.venv
bot/.venv/bin/python -m pip install --upgrade pip
bot/.venv/bin/pip install -r bot/requirements.txt

echo "Creating upload directories..."
mkdir -p admin/uploads/products admin/uploads/content admin/uploads/tests admin/uploads/profiles admin/uploads/broadcasts admin/uploads/files admin/uploads/responses

if [[ -d uploads ]]; then
  echo "Recovering misplaced uploaded files from ./uploads to ./admin/uploads..."
  cp -nR uploads/. admin/uploads/ || true
fi

if [[ -n "${APP_USER:-}" ]]; then
  echo "Applying ownership to $APP_USER:${APP_GROUP:-psacln}..."
  chown "$APP_USER:${APP_GROUP:-psacln}" "$ENV_FILE" || true
  chmod 640 "$ENV_FILE" || true
  chown -R "$APP_USER:${APP_GROUP:-psacln}" admin/uploads bot/.venv || true
fi

echo "Checking PHP syntax..."
find admin api -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >/tmp/max-app-php-lint.log
find . -maxdepth 1 -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >>/tmp/max-app-php-lint.log
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

echo "Checking the configured PHP database..."
SWPRO_ENV_FILE="$ENV_FILE" "$PHP_BIN" -r \
  'require "admin/app/core/db.php"; echo "Database: ", db()->query("SELECT DATABASE()")->fetchColumn(), PHP_EOL;'

echo "Install files prepared."
echo "Next:"
echo "  1) Existing DB: deploy/plesk/migrate-db.sh $ENV_FILE"
echo "  2) Empty disposable DB only: deploy/plesk/import-db.sh $ENV_FILE"
echo "  3) Install systemd bot service if needed: see deploy/plesk/max-app-telegram.service"
echo "  4) Open https://${APP_DOMAIN}/api/index.php"
