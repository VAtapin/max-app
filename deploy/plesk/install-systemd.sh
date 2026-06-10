#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${1:-$SCRIPT_DIR/live.env}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root: sudo bash deploy/plesk/install-systemd.sh deploy/plesk/live.env"
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing env file: $ENV_FILE"
  exit 1
fi

set -a
source "$ENV_FILE"
set +a

: "${APP_ROOT:?APP_ROOT is required}"
: "${APP_USER:?APP_USER is required}"
APP_GROUP="${APP_GROUP:-psacln}"

SERVICE_FILE=/etc/systemd/system/max-app-telegram.service

cat > "$SERVICE_FILE" <<SERVICE
[Unit]
Description=MAX App Telegram Bot
After=network.target mariadb.service mysql.service

[Service]
Type=simple
WorkingDirectory=${APP_ROOT}
EnvironmentFile=${APP_ROOT}/bot/.env
ExecStart=${APP_ROOT}/bot/.venv/bin/python -m bot.telegram.main
Restart=always
RestartSec=5
User=${APP_USER}
Group=${APP_GROUP}

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable max-app-telegram.service
systemctl restart max-app-telegram.service
systemctl status max-app-telegram.service --no-pager
