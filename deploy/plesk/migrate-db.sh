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

MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --default-character-set=utf8mb4 \
  "$DB_DATABASE" <<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
  migration VARCHAR(255) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL

applied_count=0
skipped_count=0

for migration in database/migrations/*.sql; do
  [[ -e "$migration" ]] || continue
  migration_name="$(basename "$migration")"
  if grep -Eiq '^[[:space:]]*(USE[[:space:]]+|CREATE[[:space:]]+DATABASE|DROP[[:space:]]+DATABASE)' "$migration"; then
    echo "Unsafe database selection found in $migration. The target database must come from DB_DATABASE."
    exit 1
  fi
  applied="$(
    MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
      --batch --skip-column-names \
      --host="$DB_HOST" \
      --port="$DB_PORT" \
      --user="$DB_USERNAME" \
      --default-character-set=utf8mb4 \
      "$DB_DATABASE" \
      -e "SELECT COUNT(*) FROM schema_migrations WHERE migration = '$migration_name'"
  )"
  if [[ "$applied" == "1" ]]; then
    ((skipped_count+=1))
    continue
  fi

  echo "Applying $migration..."
  MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" < "$migration"
  MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" \
    -e "INSERT INTO schema_migrations (migration) VALUES ('$migration_name')"
  ((applied_count+=1))
done

if [[ "$skipped_count" -gt 0 ]]; then
  echo "Already applied migrations: $skipped_count."
fi
echo "New migrations applied: $applied_count."
echo "Migrations complete."
