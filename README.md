# SWPro Assistant

SWPro Assistant is a multichannel lead-generation system for health product sales teams.

The MVP is not an online shop. It does not include cart, orders, payments, warehouse accounting, delivery, or financial accounting. User interest is handled through leads: consultation requests, manager contact requests, and product information requests.

## Project Structure

- `admin/` - PHP 8 admin panel without Laravel, Symfony, CMS, or ORM.
- `api/` - shared JSON API for Mini Apps, bots, and client interfaces.
- `bot/` - Telegram bot, MAX adapter, and shared Python bot logic.
- `vk-mini-app/` - current Mini App client for VK, OK, Telegram WebApp, and web-based testing.
- `mini-app/` - legacy/common Mini App path kept for compatibility.
- `database/` - SQL schema, seed data, and migrations.
- `deploy/plesk/` - live deployment scripts for Plesk hosting.

## Requirements

- PHP 8+
- MySQL 8 or MariaDB 10+
- Python 3.12+
- Apache or Nginx with PHP-FPM

No Laravel, Symfony, CMS, or ORM frameworks are used.

## Database Setup

Create the database and tables:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

Default test super-admin:

```text
Email: admin@example.com
Password: admin123
Role: superadmin
```

The password in `seed.sql` is stored as a bcrypt hash compatible with `password_verify`.

## PHP Configuration

Main configuration file:

```text
admin/app/config/config.php
```

Default database settings:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=health_sales_system
DB_USERNAME=root
DB_PASSWORD=
```

Values can be overridden through environment variables or local configuration.

Admin public directory:

```text
admin/public
```

Admin login page:

```text
/admin/public/login.php
```

## Live Deployment On Plesk

Deployment scripts for the `swpro.ru` Plesk server are located in:

```text
deploy/plesk/
```

Main deployment documentation:

```text
deploy/plesk/README.md
```

The scripts are prepared for Ubuntu 22.04, Plesk Obsidian, PHP-FPM 8.3, and MariaDB 10.6.

## Python Bots

Create a virtual environment and install dependencies:

```bash
python -m venv bot\.venv
bot\.venv\Scripts\activate
pip install -r bot\requirements.txt
copy bot\.env.example bot\.env
```

Fill `.env`:

```text
TELEGRAM_BOT_TOKEN=...
MAX_BOT_TOKEN=...
DB_HOST=127.0.0.1
DB_NAME=health_sales_system
DB_USER=root
DB_PASSWORD=...
```

Run Telegram bot:

```bash
python -m bot.telegram.main
```

MAX bot is currently prepared as an adapter and shared logic entry point:

```bash
python -m bot.max.main
```

Production MAX webhook/server loop still needs to be completed according to the final MAX Bot API flow.

## Mini App

The current Mini App client is:

```text
vk-mini-app/
```

It is used for VK, OK, Telegram WebApp, and shared client testing.

The app uses:

```text
api/auth.php
api/telegram_auth.php
api/user.php
api/products.php
api/tests.php
api/recommendations.php
api/leads.php
api/contact_manager.php
api/account_link.php
```

Telegram Mini App opens from the bot button and verifies Telegram `initData` through:

```text
api/telegram_auth.php
```

VK and OK open through the VK Mini App launch parameters. OK is detected from VK launch parameters such as `vk_client=ok`, `vk_platform`, and `vk_ok_user_id`.

If no supported platform context is available, the Mini App must not show the main functionality. It should ask the user to open the app through a supported platform.

## MVP Rules

- The MVP is lead-generation only.
- No cart, orders, payments, warehouse accounting, delivery, or finance module.
- Leads are stored in `leads`.
- Lead statuses:

```text
new
contacted
interested
closed
lost
```

- Product and content images/files are stored in the filesystem.
- The database stores only file paths, not BLOB data.
- Key actions are logged in `activity_logs`.

## Users And Platforms

One real person is represented by one row in:

```text
end_users
```

Platform accounts are stored separately in:

```text
platform_accounts
```

Supported platforms:

```text
telegram
VK
OK
MAX
web
```

One user may have several platform accounts:

```text
end_users.id = 10

platform_accounts:
- telegram / 2090702029
- VK / 37559860
- OK / 585434281343
```

## No Automatic VK/OK Merge

VK and OK accounts are not merged automatically.

The system must not merge users automatically by:

- name;
- `vk_original_vk_id`;
- HTTP referer;
- similar profile data;
- guessed identity.

Merging is allowed only in two explicit cases:

1. Manual merge in the admin panel.
2. User-confirmed platform connection through a temporary `link_token`.

## User-Confirmed Platform Connection

A user can connect another platform from the Mini App profile section.

The flow:

1. Current user opens profile.
2. System generates a temporary `link_token` through:

```text
api/account_link.php
```

3. User opens another platform using the generated link.
4. The platform account is attached to the same `end_user`.

Telegram link example:

```text
https://t.me/SWProAssistant_bot?start=link_l_123_1780000000_abcd...
```

This is not automatic recognition. It is a user-confirmed connection.

## Manual User Merge

Manual user merge is available in the admin user card.

When users are merged:

- platform accounts move to the target user;
- leads move to the target user;
- test sessions move to the target user;
- recommendations move to the target user;
- broadcast logs move to the target user;
- the source user is not deleted physically;
- the source user is marked through `merged_into_user_id`.

Migration:

```text
database/migrations/20260612_04_user_merge.sql
```

## Referral Logic

Referral links are intended for managers, not resellers.

Managers have a permanent `referral_code`.

Example links:

```text
https://t.me/SWProAssistant_bot?start=ref_ATAPIN
https://swpro.ru/mini-app/?ref=ATAPIN
```

If a user first comes through a manager referral code:

```text
end_users.manager_id = manager.id
end_users.reseller_id = manager.reseller_id
```

Primary referral binding is permanent. If the user later comes through another referral link, manager and reseller must not be changed automatically.

If the user has no manager/reseller binding, the Mini App must not show the main functionality. It should ask for a manager code or show the default manager for the current platform.

## Leads And Responses

Users can create leads from:

- manager contact request;
- product information request;
- consultation request.

For VK/OK, manager responses are delivered through the Mini App. Direct VK group messaging is postponed.

Mini App must show a "new response" marker when a user has unread manager responses.

Telegram and MAX may use direct bot delivery when configured.

## Admin Panel

The admin panel includes sections for:

- dashboard;
- resellers;
- managers;
- users;
- platform accounts;
- leads;
- product categories;
- products;
- tests;
- broadcasts;
- content/materials;
- messaging integrations.

The `platform_accounts` section is technical and may remain visible for admins. In regular work, platform accounts should also be visible inside the user card.

Super-admins can create or update admin login access for resellers and managers from their edit cards. This creates linked `admin_users` records with the `reseller` or `manager` role; managers and resellers are not end users.

## Products

Products can have:

- image;
- PDF/file;
- video URL;
- product descriptions;
- category;
- price;
- status.

Product image/file delete and replacement controls are planned as a next admin improvement.

## Tests And Recommendations

Tests are built from:

- tests;
- questions;
- answers;
- scores;
- result ranges;
- product/category/tag links.

Test submissions are stored in:

```text
user_test_sessions
user_test_answers
```

Recommendations are stored in:

```text
recommendations
```

Recommendation logic must remain centralized in API/shared services, not duplicated inside Telegram, MAX, VK, or OK clients.

## Localization

Current localization files:

```text
admin/app/lang/ru.php
vk-mini-app/i18n/ru.json
```

Localization unification is postponed and should be handled as a separate task.

## Medical Disclaimer

All product and wellness information is informational only and is not medical advice.

Do not promise treatment, cure, diagnosis, or replacement of medicines. Users should consult a qualified specialist before using products.
