#!/usr/bin/env bash
set -Eeuo pipefail

# Emergency restore helper for the old Plesk server.
#
# The Restic repository is local on the German fallback server.  The current
# Debian production server writes to it over SFTP.  This script intentionally
# restores only application data and the database; Debian/Nginx/PHP-FPM
# configuration from the new server must never be copied over Plesk.

RESTORE_ENV_FILE="${SWPRO_RESTORE_ENV:-/etc/swpro/restore.env}"
if [[ -f "$RESTORE_ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$RESTORE_ENV_FILE"
fi

RESTIC_BIN="${RESTIC_BIN:-restic}"
RESTIC_REPOSITORY="${RESTIC_REPOSITORY:-/var/backups/swpro-restic}"
RESTIC_PASSWORD_FILE="${RESTIC_PASSWORD_FILE:-/root/.config/restic/swpro.pass}"
PROJECT_ROOT="${PROJECT_ROOT:-/var/www/vhosts/swpro.ru/httpdocs}"
PRIVATE_ROOT="${PRIVATE_ROOT:-/var/www/vhosts/swpro.ru/private/swpro}"
ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/deploy/plesk/live.env}"
STAGING_ROOT="${STAGING_ROOT:-/var/backups/swpro-restore}"

export RESTIC_REPOSITORY RESTIC_PASSWORD_FILE

usage() {
  cat <<'USAGE'
Usage:
  restore-from-restic.sh list
  restore-from-restic.sh verify
  restore-from-restic.sh stage [snapshot]
  restore-from-restic.sh apply [snapshot] --confirm [--deploy]

Commands:
  list      Show available SWPro snapshots.
  verify    Check the Restic repository (reads a small data subset).
  stage     Restore a snapshot to a temporary staging directory only.
  apply     Stop the old bot, back up the current Plesk data, restore the
            database and runtime files, and leave the bot stopped.

The apply command is deliberately blocked unless --confirm is provided.
Use --deploy only after reviewing the staged snapshot and when the old site
should be prepared with the normal Plesk deploy script.
USAGE
}

die() {
  echo "ERROR: $*" >&2
  exit 1
}

require_root() {
  [[ "${EUID}" -eq 0 ]] || die "Run this command as root."
}

require_file() {
  [[ -f "$1" ]] || die "Missing file: $1"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

load_database_config() {
  require_file "$ENV_FILE"
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
  : "${DB_HOST:?DB_HOST is required in $ENV_FILE}"
  : "${DB_PORT:?DB_PORT is required in $ENV_FILE}"
  : "${DB_DATABASE:?DB_DATABASE is required in $ENV_FILE}"
  : "${DB_USERNAME:?DB_USERNAME is required in $ENV_FILE}"
  : "${DB_PASSWORD:?DB_PASSWORD is required in $ENV_FILE}"
}

restic_cmd() {
  "$RESTIC_BIN" "$@"
}

stage_snapshot() {
  local snapshot="${1:-latest}"
  local stamp stage
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  stage="$STAGING_ROOT/$stamp"
  mkdir -p "$stage"

  echo "Restoring snapshot $snapshot to: $stage"
  restic_cmd restore "$snapshot" --tag swpro --target "$stage"
  [[ -f "$stage/run/swpro-database.sql.gz" ]] || die "Snapshot has no database dump."
  [[ -d "$stage/var/www/swpro/admin/uploads" ]] || die "Snapshot has no admin/uploads directory."
  echo "Staging complete: $stage"
  echo "The staged copy includes the new-server live.env for reference only:"
  echo "  $stage/var/www/swpro/deploy/plesk/live.env"
  printf '%s\n' "$stage"
}

create_current_backup() {
  local target="$1"
  mkdir -p "$target"
  cp -a "$ENV_FILE" "$target/live.env"

  if [[ -d "$PROJECT_ROOT/admin/uploads" ]]; then
    tar -czf "$target/admin-uploads.tar.gz" -C "$PROJECT_ROOT" admin/uploads
  fi
  if [[ -d "$PROJECT_ROOT/uploads" ]]; then
    tar -czf "$target/uploads.tar.gz" -C "$PROJECT_ROOT" uploads
  fi
  if [[ -d "$PRIVATE_ROOT" ]]; then
    tar -czf "$target/private-storage.tar.gz" -C "$(dirname "$PRIVATE_ROOT")" "$(basename "$PRIVATE_ROOT")"
  fi

  local dump="$target/database.sql.gz"
  MYSQL_PWD="$DB_PASSWORD" mariadb-dump \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    "$DB_DATABASE" | gzip > "$dump"
  echo "Current Plesk state backed up to: $target"
}

restore_database() {
  local dump="$1"
  echo "Restoring database $DB_DATABASE..."
  gzip -dc "$dump" | MYSQL_PWD="$DB_PASSWORD" mariadb \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE"
}

restore_runtime_files() {
  local stage="$1"
  local source_root="$stage/var/www/swpro"

  mkdir -p "$PROJECT_ROOT/admin/uploads" "$PROJECT_ROOT/uploads" "$PRIVATE_ROOT"
  rsync -a "$source_root/admin/uploads/" "$PROJECT_ROOT/admin/uploads/"
  if [[ -d "$source_root/uploads" ]]; then
    rsync -a "$source_root/uploads/" "$PROJECT_ROOT/uploads/"
  fi
  if [[ -d "$source_root/storage/private" ]]; then
    rsync -a "$source_root/storage/private/" "$PRIVATE_ROOT/"
  fi
  chown -R "${APP_USER:-swpro}:${APP_GROUP:-psacln}" "$PROJECT_ROOT/admin/uploads" "$PROJECT_ROOT/uploads" "$PRIVATE_ROOT"
}

apply_snapshot() {
  local snapshot="${1:-latest}"
  local deploy_after_restore="${2:-0}"
  local stamp stage current_backup

  require_root
  require_command "$RESTIC_BIN"
  require_command mariadb
  require_command mariadb-dump
  require_command rsync
  require_file "$RESTIC_PASSWORD_FILE"
  load_database_config

  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  stage="$(stage_snapshot "$snapshot" | tail -n 1)"
  current_backup="/var/backups/swpro-pre-restore-$stamp"

  echo "This will replace the old Plesk database and runtime files."
  echo "Snapshot: $snapshot"
  echo "Target:   $PROJECT_ROOT"
  [[ "${CONFIRM:-}" == "YES" ]] || die "Set CONFIRM=YES or pass --confirm."

  if systemctl is-active --quiet max-app-telegram.service; then
    echo "Stopping old Telegram bot..."
    systemctl stop max-app-telegram.service
  fi

  create_current_backup "$current_backup"
  restore_database "$stage/run/swpro-database.sql.gz"
  restore_runtime_files "$stage"

  if [[ "$deploy_after_restore" == "1" ]]; then
    echo "Running the existing Plesk deploy script..."
    bash "$PROJECT_ROOT/deploy/plesk/deploy.sh" "$ENV_FILE"
  else
    echo "Restore applied. The old bot remains stopped."
    echo "Review the site, then deploy manually if needed:"
    echo "  bash $PROJECT_ROOT/deploy/plesk/deploy.sh $ENV_FILE"
  fi

  echo "Rollback material: $current_backup"
  echo "Staging material:  $stage"
}

main() {
  local command="${1:-}"
  case "$command" in
    list)
      require_root
      require_command "$RESTIC_BIN"
      require_file "$RESTIC_PASSWORD_FILE"
      restic_cmd snapshots --tag swpro
      ;;
    verify)
      require_root
      require_command "$RESTIC_BIN"
      require_file "$RESTIC_PASSWORD_FILE"
      restic_cmd check --read-data-subset=1/20
      ;;
    stage)
      require_root
      require_command "$RESTIC_BIN"
      require_file "$RESTIC_PASSWORD_FILE"
      stage_snapshot "${2:-latest}"
      ;;
    apply)
      local snapshot="${2:-latest}" deploy_after_restore=0
      [[ "${3:-}" == "--confirm" || "${4:-}" == "--confirm" ]] && export CONFIRM=YES
      [[ "${3:-}" == "--deploy" || "${4:-}" == "--deploy" ]] && deploy_after_restore=1
      [[ "${4:-}" == "--deploy" ]] && deploy_after_restore=1
      apply_snapshot "$snapshot" "$deploy_after_restore"
      ;;
    -h|--help|help)
      usage
      ;;
    *)
      usage >&2
      exit 2
      ;;
  esac
}

main "$@"
