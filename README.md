# SWPro Assistant

SWPro is a multichannel client assistant for wellness consultants and their teams. Telegram is the primary channel. VK and OK use the same lightweight Mini App without loading the Telegram SDK.

The first release is deliberately focused on four client actions:

1. Check-up of the body.
2. Cashback and gifts.
3. Contact the assigned consultant.
4. Cooperation opportunities.

The system does not sell products directly and does not include a cart, online checkout, warehouse, delivery, or automatic product recommendations.

## Roles

- `superadmin`: platform settings, leaders, subscriptions, legal documents.
- `reseller`: team leader; manages consultants, team clients and team broadcasts.
- `manager`: consultant; manages own clients, results, content and broadcasts.
- `client`: completes onboarding and the check-up through a bot or Mini App.

One paid seat is one leader account. Consultants inside that leader's team do not consume additional seats.

## Client Journey

The client is permanently assigned through the consultant referral code. Before the service opens, the client accepts the required documents and provides:

- first name;
- optional last name;
- gender;
- age or date of birth;
- city.

Client stages:

`new`, `profile_completed`, `test_started`, `test_completed`, `consultation_requested`, `in_progress`, `client`, `partner`, `inactive`, `unsubscribed`.

The 47-question health check-up is a multiscale matrix. Each positive answer can add a score to one or more of ten body-system scales. Every scale has four result ranges. Questions are shown one at a time, progress is saved after every answer, and completed results are visible to both the client and consultant.

## Main Components

- `bot/`: Telegram and MAX bot code.
- `vk-mini-app/`: Telegram WebApp, VK, OK and ordinary web client.
- `api/`: authentication, onboarding, tests, notifications and leads.
- `admin/`: leader and consultant cabinet.
- `database/`: fresh schema, seed and tracked migrations.

### Billing cron

Run workspace billing daily. It safely synchronizes workplaces, creates the previous
calendar month's invoices once, and applies overdue statuses after the configured
payment term:

```cron
15 0 * * * cd /path/to/swpro && php admin/cron/run-billing.php >> storage/logs/billing-cron.log 2>&1
```
- `index.php`: consultant public mini-site.
- `legal.php`: published legal documents.
- `deploy/plesk/`: Plesk installation and deployment scripts.

## Automations

- unfinished check-up: 24 hours, 3 days and 7 days;
- inactivity: 14 days and 30 days;
- delivery only from 10:00 to 20:00 in the client's timezone;
- no more than one automatic message per client in 24 hours.

Client broadcasts require a current marketing consent. A leader can also broadcast to consultants in the team. Messages support text, image, MP4 video and an action button.

## Local Checks

```bash
php -l api/tests.php
node --check vk-mini-app/app.js
python -m compileall -q bot
```

## Server

Use [deploy/plesk/README.md](deploy/plesk/README.md) for a clean installation and future updates.

Before production:

- replace all legal placeholders in the superadmin cabinet;
- have the legal texts reviewed for the actual operator and business model;
- configure Telegram OpenID Connect and the Mini App URL;
- add Plesk scheduled tasks for broadcasts and client automations;
- change the seeded admin password.
