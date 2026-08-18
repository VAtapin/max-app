#!/bin/bash
set -Eeuo pipefail

STATE_DIR="/var/lib/swpro-monitor"
STATE_FILE="$STATE_DIR/health-state"
ENV_FILE="/root/.config/swpro-monitoring.env"
BOOT_FILE="$STATE_DIR/boot-id"
REPORT_FILE="$STATE_DIR/last-report.txt"
JOURNAL_FILE="$STATE_DIR/critical-errors.log"
LOCK_FILE="$STATE_DIR/health.lock"

mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"

if [[ -r "$ENV_FILE" ]]; then
    # shellcheck disable=SC1090
    . "$ENV_FILE"
fi

DOMAIN="${SWPRO_MONITOR_DOMAIN:-swpro.ru}"
URL="${SWPRO_MONITOR_URL:-https://$DOMAIN/}"
ALERT_COOLDOWN="${SWPRO_ALERT_COOLDOWN_SECONDS:-1800}"
DISK_WARN="${SWPRO_DISK_WARN_PERCENT:-80}"
DISK_CRIT="${SWPRO_DISK_CRITICAL_PERCENT:-90}"
INODE_WARN="${SWPRO_INODE_WARN_PERCENT:-80}"
INODE_CRIT="${SWPRO_INODE_CRITICAL_PERCENT:-90}"
RAM_WARN="${SWPRO_RAM_WARN_AVAILABLE_PERCENT:-20}"
RAM_CRIT="${SWPRO_RAM_CRITICAL_AVAILABLE_PERCENT:-10}"
SWAP_WARN="${SWPRO_SWAP_WARN_PERCENT:-25}"
SWAP_CRIT="${SWPRO_SWAP_CRITICAL_PERCENT:-50}"
LOAD_WARN="${SWPRO_LOAD_WARN_PER_CPU:-1.5}"
LOAD_CRIT="${SWPRO_LOAD_CRITICAL_PER_CPU:-3.0}"
SSL_WARN="${SWPRO_SSL_WARN_DAYS:-30}"
SSL_CRIT="${SWPRO_SSL_CRITICAL_DAYS:-7}"
BACKUP_WARN="${SWPRO_BACKUP_WARN_AGE_HOURS:-26}"
BACKUP_CRIT="${SWPRO_BACKUP_CRITICAL_AGE_HOURS:-50}"
JOURNAL_WINDOW="${SWPRO_JOURNAL_WINDOW:-10 min ago}"
RECOVERY_ENABLED="${SWPRO_RECOVERY_ENABLED:-true}"

exec 9>"$LOCK_FILE"
flock -n 9 || exit 0

ISSUES=()
WARNINGS=()
RECOVERIES=()

send_telegram() {
    local text="$1"
    [[ -n "${TELEGRAM_BOT_TOKEN:-}" ]] || return 0
    [[ -n "${TELEGRAM_CHAT_ID:-}" ]] || return 0
    curl -fsS --connect-timeout 5 --max-time 10 -X POST \
        "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
        --data-urlencode "chat_id=${TELEGRAM_CHAT_ID}" \
        --data-urlencode "text=$text" >/dev/null || true
}

check_service() {
    local service="$1"
    if systemctl is-active --quiet "$service"; then
        return 0
    fi

    if [[ "$RECOVERY_ENABLED" == "true" ]] && [[ "$service" =~ ^(nginx|php8\.3-fpm|mariadb|swpro-telegram-tunnel)$ ]]; then
        if systemctl restart "$service" 2>/dev/null && systemctl is-active --quiet "$service"; then
            RECOVERIES+=("$service restarted successfully")
            return 0
        fi
    fi

    ISSUES+=("Service DOWN: $service")
}

for service in nginx php8.3-fpm mariadb ssh fail2ban swpro-telegram-tunnel; do
    check_service "$service"
done

HTTP_CODE="000"
HTTP_TIME="-1"
if command -v curl >/dev/null 2>&1; then
    CURL_RESULT=$(curl -4 -sS --connect-timeout 5 --max-time 10 -o /dev/null \
        -w '%{http_code}|%{time_total}' -H "Host: $DOMAIN" http://127.0.0.1/ 2>/dev/null || echo '000|-1')
    HTTP_CODE="${CURL_RESULT%%|*}"
    HTTP_TIME="${CURL_RESULT##*|}"
fi
[[ "$HTTP_CODE" == "200" ]] || ISSUES+=("SWPRO local HTTP status: $HTTP_CODE")

DNS_OK=false
DNS_IP=""
if command -v dig >/dev/null 2>&1; then
    DNS_IP=$(dig +time=3 +tries=1 +short A "$DOMAIN" @1.1.1.1 2>/dev/null | head -1 || true)
    [[ -n "$DNS_IP" ]] && DNS_OK=true
else
    DNS_IP=$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1 {print $1}' || true)
    [[ -n "$DNS_IP" ]] && DNS_OK=true
fi
$DNS_OK || ISSUES+=("DNS resolution failed: $DOMAIN")

PUBLIC_CODE="000"
PUBLIC_TIME="-1"
if command -v curl >/dev/null 2>&1; then
    PUBLIC_RESULT=$(curl -4 -sS --connect-timeout 7 --max-time 15 -o /dev/null \
        -w '%{http_code}|%{time_total}' "$URL" 2>/dev/null || echo '000|-1')
    PUBLIC_CODE="${PUBLIC_RESULT%%|*}"
    PUBLIC_TIME="${PUBLIC_RESULT##*|}"
fi
[[ "$PUBLIC_CODE" =~ ^2[0-9][0-9]$|^3[0-9][0-9]$ ]] || ISSUES+=("SWPRO external HTTPS status: $PUBLIC_CODE")

SSL_DAYS=-1
SSL_STATE="CRITICAL"
if command -v openssl >/dev/null 2>&1; then
    EXPIRY=$(echo | timeout 12 openssl s_client -connect "${DOMAIN}:443" -servername "$DOMAIN" 2>/dev/null \
        | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2 || true)
    if [[ -n "$EXPIRY" ]]; then
        EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s 2>/dev/null || echo 0)
        if (( EXPIRY_EPOCH > 0 )); then
            SSL_DAYS=$(( (EXPIRY_EPOCH - $(date +%s)) / 86400 ))
            if (( SSL_DAYS <= SSL_CRIT )); then
                SSL_STATE="CRITICAL"
                ISSUES+=("SSL certificate expires in ${SSL_DAYS} days")
            elif (( SSL_DAYS <= SSL_WARN )); then
                SSL_STATE="WARN"
                WARNINGS+=("SSL certificate expires in ${SSL_DAYS} days")
            else
                SSL_STATE="OK"
            fi
        fi
    fi
fi

read -r LOAD1 LOAD5 LOAD15 _ < /proc/loadavg
CPU_COUNT=$(nproc)
LOAD_PER_CPU=$(awk -v l="$LOAD1" -v c="$CPU_COUNT" 'BEGIN { if(c<1)c=1; printf "%.2f", l/c }')
if awk -v x="$LOAD_PER_CPU" -v c="$LOAD_CRIT" 'BEGIN {exit !(x>=c)}'; then
    ISSUES+=("Load: ${LOAD1}/${LOAD5}/${LOAD15} (${LOAD_PER_CPU}/CPU)")
elif awk -v x="$LOAD_PER_CPU" -v w="$LOAD_WARN" 'BEGIN {exit !(x>=w)}'; then
    WARNINGS+=("Load: ${LOAD1}/${LOAD5}/${LOAD15} (${LOAD_PER_CPU}/CPU)")
fi

read -r MEM_TOTAL MEM_USED MEM_FREE MEM_SHARED MEM_CACHE MEM_AVAILABLE _ < <(free -m | awk '/^Mem:/ {print $2,$3,$4,$5,$6,$7,"MB"}')
RAM_AVAILABLE_PERCENT=0
(( MEM_TOTAL > 0 )) && RAM_AVAILABLE_PERCENT=$(( MEM_AVAILABLE * 100 / MEM_TOTAL ))
if (( RAM_AVAILABLE_PERCENT <= RAM_CRIT )); then
    ISSUES+=("RAM available: ${RAM_AVAILABLE_PERCENT}%")
elif (( RAM_AVAILABLE_PERCENT <= RAM_WARN )); then
    WARNINGS+=("RAM available: ${RAM_AVAILABLE_PERCENT}%")
fi

read -r SWAP_TOTAL SWAP_USED SWAP_FREE _ < <(free -m | awk '/^Swap:/ {print $2,$3,$4,"MB"}')
SWAP_PERCENT=0
(( SWAP_TOTAL > 0 )) && SWAP_PERCENT=$(( SWAP_USED * 100 / SWAP_TOTAL ))
if (( SWAP_PERCENT >= SWAP_CRIT )); then
    ISSUES+=("Swap usage: ${SWAP_PERCENT}%")
elif (( SWAP_PERCENT >= SWAP_WARN )); then
    WARNINGS+=("Swap usage: ${SWAP_PERCENT}%")
fi

ROOT_DISK=$(df -P / | awk 'NR==2 {gsub("%","",$5); print $5}')
ROOT_INODE=$(df -Pi / | awk 'NR==2 {gsub("%","",$5); print $5}')
(( ROOT_DISK >= DISK_CRIT )) && ISSUES+=("Disk usage /: ${ROOT_DISK}%")
(( ROOT_DISK >= DISK_WARN && ROOT_DISK < DISK_CRIT )) && WARNINGS+=("Disk usage /: ${ROOT_DISK}%")
(( ROOT_INODE >= INODE_CRIT )) && ISSUES+=("Inode usage /: ${ROOT_INODE}%")
(( ROOT_INODE >= INODE_WARN && ROOT_INODE < INODE_CRIT )) && WARNINGS+=("Inode usage /: ${ROOT_INODE}%")

BACKUP_STATE="CRITICAL"
BACKUP_AGE_HOURS=-1
BACKUP_STAMP="$STATE_DIR/last-backup-success"
if [[ -f "$BACKUP_STAMP" ]]; then
    AGE=$(( $(date +%s) - $(stat -c %Y "$BACKUP_STAMP") ))
    BACKUP_AGE_HOURS=$(( AGE / 3600 ))
    if (( BACKUP_AGE_HOURS >= BACKUP_CRIT )); then
        ISSUES+=("Last successful backup is ${BACKUP_AGE_HOURS} hours old")
    elif (( BACKUP_AGE_HOURS >= BACKUP_WARN )); then
        BACKUP_STATE="WARN"
        WARNINGS+=("Last successful backup is ${BACKUP_AGE_HOURS} hours old")
    else
        BACKUP_STATE="OK"
    fi
else
    ISSUES+=("No successful backup timestamp")
fi

JOURNAL_COUNT=0
if command -v journalctl >/dev/null 2>&1; then
    journalctl -p 0..3 -b --since "$JOURNAL_WINDOW" --no-pager -o short-iso 2>/dev/null \
        | sed -E 's/(token|password|secret|api[_-]?key)=([^ ]+)/\1=[REDACTED]/gi' \
        | tail -30 > "$JOURNAL_FILE" || true
    JOURNAL_COUNT=$(grep -c . "$JOURNAL_FILE" 2>/dev/null || true)
    if (( JOURNAL_COUNT > 0 )); then
        WARNINGS+=("${JOURNAL_COUNT} critical/error journal lines in the last ${JOURNAL_WINDOW}")
    fi
else
    : > "$JOURNAL_FILE"
fi
chmod 600 "$JOURNAL_FILE"

BOOT_ID=$(cat /proc/sys/kernel/random/boot_id 2>/dev/null || echo unknown)
PREVIOUS_BOOT=""
[[ -f "$BOOT_FILE" ]] && PREVIOUS_BOOT=$(cat "$BOOT_FILE")
if [[ "$BOOT_ID" != "$PREVIOUS_BOOT" ]]; then
    if [[ -n "$PREVIOUS_BOOT" ]]; then
        send_telegram "🟢 SWPRO Server rebooted\nHostname: $(hostname -f 2>/dev/null || hostname)\nUptime: $(uptime -p 2>/dev/null || true)"
    else
        send_telegram "🟢 SWPRO Server health monitoring started\nHostname: $(hostname -f 2>/dev/null || hostname)"
    fi
    printf '%s' "$BOOT_ID" > "$BOOT_FILE"
    chmod 600 "$BOOT_FILE"
fi

{
    echo "timestamp=$(date -Is)"
    echo "hostname=$(hostname -f 2>/dev/null || hostname)"
    echo "kernel=$(uname -r)"
    echo "uptime=$(uptime -p 2>/dev/null || true)"
    echo "public_ip=$(curl -4 -sS --connect-timeout 3 --max-time 5 https://api.ipify.org 2>/dev/null || true)"
    echo "dns_ip=$DNS_IP"
    echo "local_http_code=$HTTP_CODE"
    echo "local_http_seconds=$HTTP_TIME"
    echo "public_https_code=$PUBLIC_CODE"
    echo "public_https_seconds=$PUBLIC_TIME"
    echo "ssl_days=$SSL_DAYS"
    echo "ssl_state=$SSL_STATE"
    echo "load1=$LOAD1"
    echo "load5=$LOAD5"
    echo "load15=$LOAD15"
    echo "load_per_cpu=$LOAD_PER_CPU"
    echo "ram_total_mb=$MEM_TOTAL"
    echo "ram_used_mb=$MEM_USED"
    echo "ram_available_mb=$MEM_AVAILABLE"
    echo "ram_available_percent=$RAM_AVAILABLE_PERCENT"
    echo "swap_total_mb=$SWAP_TOTAL"
    echo "swap_used_mb=$SWAP_USED"
    echo "swap_percent=$SWAP_PERCENT"
    echo "disk_root_percent=$ROOT_DISK"
    echo "inode_root_percent=$ROOT_INODE"
    echo "backup_age_hours=$BACKUP_AGE_HOURS"
    echo "backup_state=$BACKUP_STATE"
    echo "journal_error_count=$JOURNAL_COUNT"
    echo "recovery_enabled=$RECOVERY_ENABLED"
    echo "service_nginx=$(systemctl is-active nginx 2>/dev/null || true)"
    echo "service_php83_fpm=$(systemctl is-active php8.3-fpm 2>/dev/null || true)"
    echo "service_mariadb=$(systemctl is-active mariadb 2>/dev/null || true)"
    echo "service_ssh=$(systemctl is-active ssh 2>/dev/null || true)"
    echo "service_fail2ban=$(systemctl is-active fail2ban 2>/dev/null || true)"
    echo "service_swpro_telegram_tunnel=$(systemctl is-active swpro-telegram-tunnel 2>/dev/null || true)"
    echo "restic_timer=$(systemctl is-active swpro-restic-backup.timer 2>/dev/null || true)"
    echo "disk_report_begin=1"
    df -P -x tmpfs -x devtmpfs
    echo "disk_report_end=1"
    echo "inode_report_begin=1"
    df -Pi -x tmpfs -x devtmpfs
    echo "inode_report_end=1"
} > "$REPORT_FILE"
chmod 600 "$REPORT_FILE"

if (( ${#ISSUES[@]} > 0 )); then
    CURRENT="CRITICAL"$'\n'$(printf '%s\n' "${ISSUES[@]}")
elif (( ${#WARNINGS[@]} > 0 )); then
    CURRENT="WARN"$'\n'$(printf '%s\n' "${WARNINGS[@]}")
else
    CURRENT="OK"
fi

if [[ -n "${SWPRO_HEARTBEAT_URL:-}" ]] && command -v curl >/dev/null 2>&1; then
    HEARTBEAT_MSG="SWPRO health ${CURRENT}"
    if curl -fsS --connect-timeout 5 --max-time 10 \
        --get --data-urlencode "status=up" \
        --data-urlencode "msg=${HEARTBEAT_MSG:0:200}" \
        "$SWPRO_HEARTBEAT_URL" >/dev/null 2>&1; then
        echo "heartbeat_transport=ok" >> "$REPORT_FILE"
    else
        echo "heartbeat_transport=failed" >> "$REPORT_FILE"
    fi
fi

PREVIOUS=""
[[ -f "$STATE_FILE" ]] && PREVIOUS=$(cat "$STATE_FILE")

NOW=$(date +%s)
LAST_ALERT_FILE="$STATE_DIR/last-alert"
LAST_ALERT_TIME=0
if [[ -f "$LAST_ALERT_FILE" ]]; then
    read -r LAST_ALERT_TIME _ < "$LAST_ALERT_FILE" || true
fi
CURRENT_HASH=$(printf '%s' "$CURRENT" | sha256sum | awk '{print $1}')
SHOULD_ALERT=false
if [[ "$CURRENT" != "$PREVIOUS" ]]; then
    SHOULD_ALERT=true
elif [[ "$CURRENT" != "OK" ]] && (( NOW - LAST_ALERT_TIME >= ALERT_COOLDOWN )); then
    SHOULD_ALERT=true
fi

if [[ "$SHOULD_ALERT" == "true" ]]; then
    if [[ "$CURRENT" == "OK" ]]; then
        if [[ -n "$PREVIOUS" && "$PREVIOUS" != "OK" ]]; then
            send_telegram "🟢 SWPRO RECOVERED\nAll monitored checks are OK."
        fi
    else
        ICON="⚠️"
        [[ "$CURRENT" == CRITICAL* ]] && ICON="🔴"
        send_telegram "${ICON} SWPRO ALERT\n${CURRENT}"
    fi
    printf '%s %s\n' "$NOW" "$CURRENT_HASH" > "$LAST_ALERT_FILE"
fi

printf '%s' "$CURRENT" > "$STATE_FILE"
chmod 600 "$STATE_FILE"

if (( ${#RECOVERIES[@]} > 0 )); then
    printf '%s\n' "${RECOVERIES[@]}" > "$STATE_DIR/last-recovery"
    chmod 600 "$STATE_DIR/last-recovery"
    send_telegram "🔄 SWPRO recovery\n$(printf '%s\n' "${RECOVERIES[@]}")"
fi

if [[ "$CURRENT" == "OK" ]]; then
    echo "SWPRO health OK"
    exit 0
fi
printf '%b\n' "$CURRENT"
exit 1
