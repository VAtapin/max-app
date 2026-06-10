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

Fill DB credentials, Telegram token and system user.

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

This creates:

- `admin/app/config/local.php`
- `bot/.env`
- `bot/.venv`
- upload folders

Private files are ignored by Git.

Run this again after updates that add new private config values. Telegram Mini App requires the bot token in `admin/app/config/local.php` to verify `initData`.

## 6. Import Database

```bash
bash deploy/plesk/import-db.sh deploy/plesk/live.env
```

Default admin after seed:

```text
Email: admin@example.com
Password: admin123
```

Change it after first login.

## 7. Protect Private Paths

The repository includes `.htaccess`. For Plesk/nginx, also paste:

- `deploy/plesk/apache-additional.conf` into Additional Apache directives
- `deploy/plesk/nginx-additional.conf` into Additional nginx directives

Then reload Apache/nginx from Plesk.

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

## 10. Smoke Test URLs

```text
https://swpro.ru/api/index.php
https://swpro.ru/admin/public/login.php
https://swpro.ru/mini-app/index.html
https://swpro.ru/vk-mini-app/index.html
```

API check:

```bash
curl -s https://swpro.ru/api/index.php
```
