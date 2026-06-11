#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="${1:-$SCRIPT_DIR/live.env}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing env file: $ENV_FILE"
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

MYSQL_BIN="${MYSQL_BIN:-mysql}"

cd "$PROJECT_ROOT"

if [[ ! -d database/migrations ]]; then
  echo "No migrations directory."
  exit 0
fi

for migration in database/migrations/*.sql; do
  [[ -e "$migration" ]] || continue
  echo "Applying $migration..."
  MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    "$DB_DATABASE" < "$migration"
done

echo "Migrations complete."
