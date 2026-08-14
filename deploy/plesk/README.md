# Plesk Live Deploy For swpro.ru

Target server:

- Plesk Obsidian on Ubuntu 22.04
- Domain: `swpro.ru`
- Document root: `/var/www/vhosts/swpro.ru/httpdocs`
- PHP: 8.3.x, PHP-FPM
- DB: MariaDB 10.6.x

## 1. Prepare Domain

In Plesk, set the domain document root to:

```text
httpdocs
```

Enable PHP-FPM with PHP 8.3.

Upload limits are controlled by PHP/Plesk. Example values for images, PDFs and videos:

```text
upload_max_filesize = 300M
post_max_size = 320M
```

Recommended PHP extensions:

```text
pdo_mysql
mbstring
json
openssl
curl
fileinfo
```

## 2. Upload Code

Inside `/var/www/vhosts/swpro.ru/httpdocs`:

```bash
git clone https://github.com/VAtapin/max-app.git .
```

If the folder is not empty, upload files or pull into the existing Git checkout.

## 3. Create Database In Plesk

Create a MariaDB database and user in Plesk, for example:

```text
Database: health_sales_system
User: max_app
Password: strong password
```

MariaDB 10.6 is enough for the current schema.

## 4. Configure Live Env

```bash
cd /var/www/vhosts/swpro.ru/httpdocs
cp deploy/plesk/env.example deploy/plesk/live.env
nano deploy/plesk/live.env
```

Fill DB credentials, Telegram token, public URLs, system user and
`SWPRO_PRIVATE_STORAGE_PATH`. The private path should be outside `httpdocs`, for
example `/var/www/vhosts/swpro.ru/private/swpro`; it stores avatar source media
and generated previews that must not be publicly addressable. This is the
only runtime configuration file used by the PHP application, bots, migrations
and systemd service. Do not create `admin/app/config/local.php`, `bot/.env` or
a root `.env`.

For AI features, set `OPENAI_API_KEY` for text generation and voice. Real AI
video additionally requires either `HEYGEN_API_KEY` or `TAVUS_API_KEY`; select
the same provider in the super-admin AI settings after deployment. Generated
MP3 and MP4 files are stored under `SWPRO_PRIVATE_STORAGE_PATH`.

For Telegram OpenID Connect on ordinary desktop/mobile web:

- set `TELEGRAM_OIDC_CLIENT_ID` and `TELEGRAM_OIDC_CLIENT_SECRET`;
- register `https://swpro.ru/api/telegram_oidc_callback.php` as the redirect URL;
- keep `SWPRO_PUBLIC_URL=https://swpro.ru`;
- keep `SWPRO_MINI_APP_URL=https://swpro.ru/vk-mini-app/`.

For VK:

- `VK_APP_ID` and `VK_SECURE_KEY` are for VK Mini App authorization.
- `VK_GROUP_TOKEN` is required to send replies from the admin panel to VK users. Create it in VK community settings: API access keys, with community messages permission.
- `VK_SERVICE_TOKEN` is not enough for `messages.send`; VK rejects that method for service tokens.

Common Plesk system user can be checked with:

```bash
stat -c '%U %G' /var/www/vhosts/swpro.ru/httpdocs
```

Put those values into `APP_USER` and `APP_GROUP`.

## 5. Run Install

```bash
cd /var/www/vhosts/swpro.ru/httpdocs
bash deploy/plesk/install.sh deploy/plesk/live.env
```

This creates `bot/.venv`, public upload folders and the private AI media folder,
validates the database connection, and removes legacy configuration copies. Private `deploy/plesk/live.env` is
ignored by Git. Telegram Mini App reads its bot token from this file to verify
`initData`.

## 6. Import Database

```bash
bash deploy/plesk/import-db.sh deploy/plesk/live.env
```

The clean import loads the current schema, demo data, the 47-question multiscale check-up and its media metadata.

Default admin after seed:

```text
Email: admin@example.com
Password: admin123
```

Change it after first login.

For later schema updates without wiping data, run migrations instead of importing the full schema. Applied migrations are tracked and are not repeated:

```bash
bash deploy/plesk/migrate-db.sh deploy/plesk/live.env
```

## 7. Protect Private Paths

The repository includes `.htaccess`. For Plesk/nginx, also paste:

- `deploy/plesk/apache-additional.conf` into Additional Apache directives
- `deploy/plesk/nginx-additional.conf` into Additional nginx directives

Then reload Apache/nginx from Plesk.

Avatar files are returned only by the authenticated owner-only PHP endpoint.
The bundled web-server rules also deny `/storage/private` in case the fallback
folder under the project root is ever used.

Add a daily cron task for the retention periods configured in
`Настройки → Настройки ИИ`:

```bash
php /var/www/vhosts/swpro.ru/httpdocs/admin/cron/cleanup-ai-data.php
```

Use `--dry-run` to preview counts without deleting records.

The standard deploy also runs `admin/cron/sync-docsify-ai.php` after migrations,
so committed Docsify pages become searchable by the admin assistant without a
second manual copy.

## 8. Telegram Bot Service

Install generated service from `live.env`:

```bash
sudo bash deploy/plesk/install-systemd.sh deploy/plesk/live.env
sudo systemctl status max-app-telegram.service
```

Logs:

```bash
journalctl -u max-app-telegram.service -f
```

Telegram diagnostics:

```bash
bash deploy/plesk/check-telegram.sh
```

Set Telegram bot menu button:

```bash
bot/.venv/bin/python -m bot.telegram.set_menu_button
```

Reset custom menu button when BotFather already provides the Mini App Open button:

```bash
bot/.venv/bin/python -m bot.telegram.clear_menu_button
```

## 9. Update Deploy

For later updates:

```bash
cd /var/www/vhosts/swpro.ru/httpdocs
bash deploy/plesk/deploy.sh deploy/plesk/live.env
```

The deploy script pulls code, applies migrations, checks syntax, updates bot dependencies and restarts the bot service when the service is installed.

## 10. Scheduled Tasks

Create four Plesk scheduled tasks under the site system user:

```cron
*/5 * * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/run-broadcasts.php
*/15 * * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/run-automations.php
15 0 * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/run-billing.php >> storage/logs/billing-cron.log 2>&1
35 0 * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/cleanup-web-users.php >> storage/logs/web-user-cleanup.log 2>&1
```

The second task sends unfinished check-up reminders after 24 hours, 3 days and 7 days, and inactivity messages after 14 and 30 days. Delivery is limited to daytime in the client's timezone. The third task creates invoices for the previous calendar month and updates individual workplace access after the payment deadline. The fourth task deletes abandoned temporary web profiles after the configured 3-day or 5-day period.

## 11. Smoke Test URLs

```text
https://swpro.ru/api/index.php
https://swpro.ru/admin/public/login.php
https://swpro.ru/vk-mini-app/
https://swpro.ru/docs/
https://swpro.ru/legal.php?type=privacy_policy
```

API check:

```bash
curl -s https://swpro.ru/api/index.php
```
