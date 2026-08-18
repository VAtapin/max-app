# SWPRO server Telegram commands

This is the command contract for the existing SWPRO Telegram bot. It is intentionally separate from the server health script.

## Authorization

Every command in this document is administrator-only. Authorize by immutable Telegram user ID, not by username.

Never expose these commands to ordinary bot users.

## Commands

### `/status`

Compact status:

```text
🟢 SWPRO
Website       🟢 200 / 0.08s
SSL           🟢 118 days
Nginx         🟢
PHP-FPM       🟢
MariaDB       🟢
SSH           🟢
Fail2ban      🟢
Telegram      🟢
Backup        🟢 3h ago
Disk          🟢 3%
RAM           🟢 78% available
Load          🟢 0.12/4 CPU
```

### `/server`

Show:

- hostname;
- public IPv4;
- Debian/OS release;
- kernel;
- uptime;
- CPU count.

### `/resources`

Show:

- load 1/5/15;
- load per CPU;
- RAM total/used/available;
- swap total/used/percentage.

### `/disk`

Show all non-temporary filesystems:

- filesystem;
- mount point;
- size;
- used;
- available;
- percent;
- inode usage.

### `/services`

Show:

- nginx;
- php8.3-fpm;
- mariadb;
- ssh;
- fail2ban;
- swpro-telegram-tunnel;
- swpro-health.timer;
- swpro-restic-backup.timer.

### `/website`

Show:

- DNS A record;
- local HTTP status and latency;
- public HTTPS status and latency.

### `/ssl`

Show:

- certificate subject;
- issuer;
- expiry date;
- remaining days;
- state OK/WARN/CRITICAL.

### `/backup`

Show:

- last successful Restic timestamp;
- age;
- freshness state;
- backup timer state.

Do not expose Restic repository credentials or command lines containing secrets.

### `/logs`

Show only a short, redacted tail of:

```text
/var/lib/swpro-monitor/critical-errors.log
```

Do not run unrestricted `journalctl` output through Telegram. Redact at least `token`, `password`, `secret`, `api_key`, authorization headers and obvious private-key material.

### `/recovery`

Show the last fixed-service recovery actions from:

```text
/var/lib/swpro-monitor/last-recovery
```

The command must not accept a service argument.

### `/health`

One-line/short form for quick checks.

### `/all`

Full report assembled from:

```text
/var/lib/swpro-monitor/last-report.txt
/var/lib/swpro-monitor/health-state
/var/lib/swpro-monitor/last-recovery
```

Keep the response readable and split into multiple Telegram messages if needed. Do not expose secrets.

## Data source

The bot should prefer the saved report produced by `/usr/local/sbin/swpro-health.sh`. It should not execute arbitrary shell commands on every request.

The health timer runs every five minutes, so command output represents the latest completed check. If the report is older than a configurable freshness threshold, show `⚠️ data is stale` rather than pretending it is current.

## Future write/recovery commands

Do not add `/restart`, `/exec`, `/shell`, `/command` or similar generic commands.

Automatic recovery is controlled only by the fixed allowlist in `swpro-health.sh` and by `SWPRO_RECOVERY_ENABLED`.
