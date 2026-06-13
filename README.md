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
api/content.php
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

The Mini App is designed as the client's mobile space with their consultant. The first screen should show the consultant profile, not a technical menu:

- consultant photo or initials;
- consultant name, title, subtitle, and short description;
- contact action;
- embedded YouTube video when available;
- short "about consultant" cards;
- recommended tests;
- consultant products;
- useful materials;
- user leads and unread manager responses.

Product and material cards open inside the Mini App as detail screens. A detail screen can show:

- main image;
- category or content type;
- short and full text;
- embedded YouTube video when the URL is supported;
- uploaded file/PDF link;
- external button link;
- manager contact action.

The client should not receive only a short technical line for a product or material. The Mini App must present the item as useful consultant content.

Current Mini App UI rules:

- the first screen is consultant-first, with quick actions below the consultant block;
- navigation is compact and mobile-friendly;
- cards use the same visual language for products, tests, materials, recommendations, and leads;
- lead responses are displayed as separated message blocks, not as one merged text string;
- unread manager responses are highlighted with an accent marker;
- technical API errors should be converted into friendly user messages.
- "Ask a question" and "Write to manager" open a modal with a message field. A lead is created only after the user writes and sends their own text.
- tests open inside the current Mini App view, load questions, validate required answers, and submit results through the shared API.
- materials open inside the current Mini App view by content id instead of redirecting the user to a VK wrapper page.
- recommendations show the reason, description, full product text, files, video, and product actions when available.

The Mini App applies the consultant profile theme through `profile.theme_key`. Initial themes are `classic`, `ocean`, `berry`, and `graphite`.

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

Telegram referral links use the Bot API `start` parameter. Telegram sends it to the bot as `/start ref_CODE`. If the Telegram user already exists but has no manager/reseller binding yet, the bot attaches the user to the manager from that referral code. Existing primary binding is still permanent and is not overwritten.

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

The admin lead list is designed for daily processing rather than a raw table dump:

- filters by lead status, platform, and response state;
- paginated list to avoid huge pages with thousands of leads;
- compact cards with user, platform, product, manager, reseller, response count, and latest response preview;
- lead edit screen with a response history timeline;
- response history separates message text, selected material, selected test, uploaded files, external link, delivery status, and delivery error.

## Broadcasts

Broadcasts are MVP-level scheduled messages.

The admin section can create a broadcast and run it manually from the broadcast list. Delivery attempts are written into:

```text
broadcast_logs
```

Telegram recipients are sent through the configured Telegram Bot API token. VK, OK, MAX, and web recipients are currently recorded as internal/Mini App delivery records.

Scheduled broadcasts can be processed by a server cron command:

```bash
php admin/cron/run-broadcasts.php
```

The cron script processes broadcasts with `status = scheduled` and `scheduled_at <= NOW()`. Recurring broadcasts with `daily`, `weekly`, or `monthly` schedule type are moved to the next scheduled date after each run.

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
- messaging integrations;
- help and FAQ.

The `platform_accounts` section is technical and may remain visible for admins. In regular work, platform accounts should also be visible inside the user card.

Super-admins can create or update admin login access for resellers and managers from their edit cards. This creates linked `admin_users` records with the `reseller` or `manager` role; managers and resellers are not end users.

The admin UI is intended to work as a practical CRM-style workspace:

- the active menu item is highlighted;
- the top bar shows the current section and logged-in user;
- tables, forms, filters, alerts, cards, and action buttons use one consistent style;
- destructive actions stay explicit and should remain easy to notice;
- large operational sections such as leads should avoid raw endless tables and use filters, pagination, and compact cards.
- dashboard statistic cards are clickable and open the related admin section.
- the user edit screen does not render the full user list below the edit form. It keeps the user form, connected platform accounts, and merge actions.

## Admin Deletion Rules

Admin deletion is intentionally narrow. Deleting one record must not delete other people from the structure.

- Deleting an end user removes only that end user. Platform accounts, leads, test sessions, recommendations, and broadcast logs are removed through database relations.
- Deleting a manager removes only the manager record and its own service records: admin access, referral links, messaging integrations, and consultant profile. Assigned users remain in the database and are detached from that manager.
- Deleting a reseller removes only the reseller record and its own service records: admin access, referral links, messaging integrations, and consultant profile. Managers and users remain in the database and are detached from that reseller.
- Products, tests, product categories, and content owned by a deleted manager or reseller are not deleted. Their owner fields are cleared so the content can be reassigned later.

## Consultant Pages

SWPro is centered around the consultant, not around a shop catalog.

Each manager or reseller can have a public consultant page. The profile is stored in:

```text
consultant_profiles
profile_blocks
profile_products
profile_tests
profile_materials
profile_reviews
```

The admin section "My Page" allows a consultant profile to define:

- display name;
- public slug;
- title and subtitle;
- short description;
- photo and banner;
- video URL;
- about text;
- specialization;
- experience;
- certificates;
- achievements;
- contacts;
- enabled page blocks;
- selected products, tests, and materials.

The public website root can open a profile by slug or referral code:

```text
https://swpro.ru/?m=consultant-slug
https://swpro.ru/?ref=MANAGER-CODE
```

The page renders only filled sections. Empty biography, specialization, experience, certificates, and achievement blocks are not shown.

The public consultant page includes a top navigation bar and Mini App entry links. When a referral code is known, links to the Mini App include that code so the client context remains connected to the consultant.

When the website is opened without a consultant slug or referral code, it shows the SWPro public landing page. The landing page explains the consultant-first model and keeps the referral code form as the primary action.

Current public site UI rules:

- the public landing page explains SWPro as a consultant-led system, not as a shop;
- the consultant page starts with the consultant profile, contact action, and useful counters;
- YouTube video URLs are embedded directly when possible;
- products, tests, materials, reviews, and contact blocks are shown only when they have content;
- Mini App links keep the referral context when a consultant referral code is available.
- the page applies the consultant profile theme through `profile.theme_key`.

## Platform Linking

Client account linking is explicit. VK, OK, Telegram, and MAX are not merged automatically by name or indirect platform hints.

The Mini App profile can request an account-link token through:

```text
POST /api/account_link.php
```

When the user opens the returned link on another platform and authenticates there, that platform account is attached to the original end user. If the second platform had already created a separate end user for the same person, the confirmed link token merges that second user into the original profile.

This is a user-confirmed linking flow, not automatic VK/OK recognition.

## Products

Products can have:

- image;
- PDF/file;
- video URL;
- product descriptions;
- category;
- price;
- status.

Product and material forms support file replacement and removal from the card:

- a new upload replaces the current file path;
- "remove current file" clears the database path;
- files are stored in the filesystem;
- the database stores only paths.

The current implementation does not physically delete old uploaded files from disk when a path is removed from a card. This avoids accidental data loss during MVP development.

Client-facing product API supports detail mode:

```text
GET /api/products.php?id=PRODUCT_ID&platform=...&platform_user_id=...
```

The Mini App uses this endpoint for the product detail screen.

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

The seed data includes starter wellness tests inspired by common nutrition and lifestyle assessment themes:

- energy and fatigue;
- sleep and recovery;
- skin and hair beauty;
- nutrition and micronutrients;
- immunity and stress.

Each starter test includes questions, answer options, scores, and result ranges. The result text is informational and should lead the user toward a consultant conversation, not toward medical diagnosis.

Required test questions are validated both in the Mini App and in the API. A test cannot be submitted if required questions are not answered.

The test list API returns category title and question count so the Mini App can display tests as human-readable diagnostics instead of plain technical rows.

The admin test builder supports editing the full test structure from one screen:

- question text, type, required flag, and sort order;
- answer text, score, linked product/category, and sort order;
- result title, score range, summary, advice, linked product/category, and sort order;
- add/delete actions for questions, answers, and results;
- quick counters for questions, answers, and result ranges.

## Help And FAQ

The admin panel has a manager-facing Help/FAQ page:

```text
admin/public/help.php
```

FAQ content is stored in the database, not hardcoded in PHP or language files:

```text
help_faq_sections
```

The page reads active FAQ sections from the database. One section can be marked as featured and displayed as the intro block. Other sections are displayed as FAQ cards.

Managers can edit FAQ sections directly from the Help page. FAQ text is saved into the database table; PHP only renders the data.

Seed FAQ topics currently cover:

- core SWPro idea;
- leads;
- tests;
- products and materials;
- client platforms;
- referral links.

For an existing live server, the current FAQ and starter test data can be applied without re-importing the whole seed file:

```text
database/live_update_20260613_help_tests.sql
```

This SQL file creates the FAQ table if needed, refreshes the seeded FAQ sections, and inserts the starter tests, questions, answers, and result ranges when they do not already exist.

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
