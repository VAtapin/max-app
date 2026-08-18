# SWPRO Server Monitoring

This directory contains the production server-side monitoring files for the SWPRO Debian server.

**Important:** these files are operational infrastructure, not Laravel application code. They live under `ops/server-monitoring/` in Git only so the exact production setup is documented and can be restored after a rebuild. They are installed into `/usr/local/sbin`, `/etc/systemd/system` and `/etc` on the server.

## Current production architecture

```text
/etc/systemd/system/swpro-health.timer
        |
        | every 5 minutes
        v
/etc/systemd/system/swpro-health.service
        |
        v
/usr/local/sbin/swpro-health.sh
        |
        +--> /var/lib/swpro-monitor/health-state
        +--> /var/lib/swpro-monitor/last-report.txt
        +--> /var/lib/swpro-monitor/critical-errors.log
        +--> /var/lib/swpro-monitor/last-backup-success
        +--> /var/lib/swpro-monitor/boot-id
        +--> /var/lib/swpro-monitor/last-alert
        +--> /var/lib/swpro-monitor/last-recovery
```

The timer is enabled and currently runs two minutes after boot and then every five minutes:

```ini
OnBootSec=2min
OnUnitActiveSec=5min
AccuracySec=30s
```

The service is deliberately a simple `Type=oneshot` unit:

```ini
[Unit]
Description=SWPRO Server Health Check

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/swpro-health.sh
```

Do not create a second health service or a second monitoring daemon without updating this document first.

## What the health check monitors

### Services

The fixed service list is:

- nginx
- php8.3-fpm
- mariadb
- ssh
- fail2ban
- swpro-telegram-tunnel

For `nginx`, `php8.3-fpm`, `mariadb` and `swpro-telegram-tunnel`, automatic recovery is allowed when `SWPRO_RECOVERY_ENABLED=true`.

`ssh` and `fail2ban` are monitored but are **not** automatically restarted.

Recovery never accepts a service name from Telegram or another external input. The allowlist is hard-coded in the script.

### Website

The check performs two HTTP checks:

1. local HTTP through `127.0.0.1` with `Host: swpro.ru`;
2. public HTTPS request to `https://swpro.ru/` from the server.

The report stores HTTP status and response time.

The public check is a check from the SWPRO server to its public hostname. It is not an independent Internet vantage point. For a real independent external monitor, configure `SWPRO_HEARTBEAT_URL` to a remote push monitor.

### DNS

When `dig` is available, the script asks Cloudflare's public resolver `1.1.1.1` for the A record. Otherwise it falls back to the local resolver.

### SSL

The certificate served for `swpro.ru:443` is checked with `openssl`.

Default thresholds:

- WARN: 30 days or less;
- CRITICAL: 7 days or less.

### Disk and inode

Root filesystem thresholds:

- WARN: 80%;
- CRITICAL: 90%.

The complete `df` and inode tables are stored in `last-report.txt` for later Telegram reporting.

### RAM / swap / load

RAM is evaluated using **available** memory, not simply the Linux `used` value.

Defaults:

- RAM available WARN: below 20%;
- RAM available CRITICAL: below 10%;
- swap WARN: 25% used;
- swap CRITICAL: 50% used;
- load WARN: 1.5 per CPU;
- load CRITICAL: 3.0 per CPU.

### Restic

The monitor keeps the existing convention:

```text
/var/lib/swpro-monitor/last-backup-success
```

The Restic backup process must update this file **only after a successful backup**.

Default freshness thresholds:

- WARN: 26 hours;
- CRITICAL: 50 hours.

The health script does not pretend that a backup succeeded merely because a timer exists.

### System errors

Recent priority 0..3 journal entries from the current boot are collected for the configured window (default: 10 minutes) into:

```text
/var/lib/swpro-monitor/critical-errors.log
```

The stored sample is redacted for obvious `token=`, `password=`, `secret=` and `api_key=` patterns and is limited to the last 30 lines.

The Telegram `/logs` command must never expose full raw logs or secrets.

### Reboot / startup

The kernel boot ID is stored in:

```text
/var/lib/swpro-monitor/boot-id
```

After a new boot, the next health run sends a single Telegram startup/reboot notification. It does not send the notification every five minutes.

### Heartbeat

The script supports an optional remote push URL:

```text
SWPRO_HEARTBEAT_URL=https://REMOTE-MONITOR/...secret...
```

If configured, every health run sends a heartbeat to that remote endpoint. A remote push monitor can then detect a completely dead server because no heartbeat arrives. The secret URL must never be committed to Git.

A Uptime Kuma Push monitor is one suitable implementation: its documented push endpoint accepts a secret push token and `status`, `msg` and `ping` parameters. See the official Uptime Kuma documentation before configuring it.

### Telegram alert deduplication

The monitor stores the previous state and last alert timestamp. Identical failures are not sent every five minutes.

A state change is sent immediately. Repeated unchanged failures are limited by `SWPRO_ALERT_COOLDOWN_SECONDS` (default 30 minutes).

When the state returns to OK, one recovery notification is sent.

## State model

The overall health is one of:

- `OK`
- `WARN`
- `CRITICAL`

CRITICAL has priority over WARN.

The current state is stored in:

```text
/var/lib/swpro-monitor/health-state
```

The machine-readable report used by future Telegram commands is:

```text
/var/lib/swpro-monitor/last-report.txt
```

This is intentionally separate from the application and contains no passwords or bot tokens.

## Telegram commands

The monitoring layer provides the data. The Telegram bot should expose these read-only commands to configured administrator Telegram IDs:

| Command | Output |
|---|---|
| `/status` | Compact overall health and every check state |
| `/server` | Hostname, public IP, OS, kernel, uptime |
| `/resources` | CPU count, load, RAM and swap |
| `/disk` | Filesystems, used/free space and inode usage |
| `/services` | nginx, PHP-FPM, MariaDB, SSH, Fail2ban and Telegram tunnel |
| `/website` | DNS, local HTTP, public HTTPS and response time |
| `/ssl` | Certificate subject/issuer, expiry and remaining days |
| `/backup` | Last successful Restic backup and age |
| `/logs` | Short, redacted recent critical journal errors |
| `/recovery` | Last automatic service recovery actions |
| `/health` | Very short health summary |
| `/all` | Full server report |

Security requirements for the bot:

- only configured administrator Telegram IDs may use these commands;
- never expose `.env`, passwords, bot tokens, SSH keys or private keys;
- `/logs` must redact secrets and truncate output;
- commands are read-only;
- no Telegram command may become an arbitrary shell command;
- `/recovery` may report recovery results but must not accept arbitrary service names;
- use the saved report/state files instead of running expensive checks for every command.

The actual bot implementation is intentionally kept separate from this server-monitoring bundle. The command contract is documented here so it can be implemented later without rediscovering the server architecture.

## Installation / restore after a new server build

Copy the script to the production path:

```bash
install -o root -g root -m 0750 \
  ops/server-monitoring/usr/local/sbin/swpro-health.sh \
  /usr/local/sbin/swpro-health.sh
```

Install the systemd units:

```bash
install -o root -g root -m 0644 \
  ops/server-monitoring/etc/systemd/system/swpro-health.service \
  /etc/systemd/system/swpro-health.service

install -o root -g root -m 0644 \
  ops/server-monitoring/etc/systemd/system/swpro-health.timer \
  /etc/systemd/system/swpro-health.timer
```

Create the production environment file manually; **do not copy real secrets from Git**:

```bash
install -d -o root -g root -m 0700 /root/.config
nano /root/.config/swpro-monitoring.env
chmod 600 /root/.config/swpro-monitoring.env
```

Reload and enable the timer:

```bash
systemctl daemon-reload
systemctl enable --now swpro-health.timer
```

Run a manual check:

```bash
systemctl start swpro-health.service
systemctl status swpro-health.service --no-pager
```

Inspect the timer:

```bash
systemctl status swpro-health.timer --no-pager
systemctl list-timers --all | grep swpro-health
```

Inspect the monitor state:

```bash
cat /var/lib/swpro-monitor/health-state
cat /var/lib/swpro-monitor/last-report.txt
```

## Production environment

The example configuration is:

```text
ops/server-monitoring/etc/swpro-monitoring.env.example
```

Production secrets stay only in:

```text
/root/.config/swpro-monitoring.env
```

Never commit that production file.

## Git rule

The server-monitoring files are stored under `ops/server-monitoring/` so they do not mix with Laravel application code.

When the server changes, update the corresponding file here at the same time. The goal is that a new administrator can rebuild the monitoring from this directory without searching old chat messages.
