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

assert_database_neutral_sql() {
  local sql_file="$1"
  if grep -Eiq '^[[:space:]]*(USE[[:space:]]+|CREATE[[:space:]]+DATABASE|DROP[[:space:]]+DATABASE)' "$sql_file"; then
    echo "Unsafe database selection found in $sql_file. The target database must come from DB_DATABASE."
    exit 1
  fi
}

assert_database_neutral_sql database/schema.sql
assert_database_neutral_sql database/seed.sql
for sql_file in database/migrations/*.sql; do
  [[ -e "$sql_file" ]] || continue
  assert_database_neutral_sql "$sql_file"
done

echo "Importing schema into ${DB_DATABASE}..."
MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --default-character-set=utf8mb4 \
  "$DB_DATABASE" < database/schema.sql

echo "Importing seed data..."
MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --default-character-set=utf8mb4 \
  "$DB_DATABASE" < database/seed.sql

echo "Importing the multiscale check-up and its media metadata..."
for migration in \
  database/migrations/20260618_01_multiscale_health_test.sql \
  database/migrations/20260618_02_admin_multiscale_tests.sql \
  database/migrations/20260618_03_test_intro_progress_media.sql; do
  MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" < "$migration"
done

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

UPDATE content_posts
SET section_type = 'program'
WHERE title = 'Персональная программа с консультантом';

UPDATE test_questions
SET gender_scope = 'female'
WHERE question_text IN (
  'Для женщин: проблемы с менструальным циклом',
  'Для женщин: период менопаузы, «приливы»'
);

INSERT IGNORE INTO profile_tests (profile_id, test_id, sort_order)
SELECT cp.id, t.id, 10
FROM consultant_profiles cp
INNER JOIN tests t ON t.title = 'Диагностика организма' AND t.is_active = 1;

INSERT IGNORE INTO schema_migrations (migration) VALUES
('20260618_01_multiscale_health_test.sql'),
('20260618_02_admin_multiscale_tests.sql'),
('20260618_03_test_intro_progress_media.sql'),
('20260703_01_simplified_client_journey.sql'),
('20260703_02_referral_links_only.sql');
SQL

echo "Database import complete."
