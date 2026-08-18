# Production server — Debian 12

Этот документ описывает актуальную production-инфраструктуру SWPro после переноса с Plesk на отдельный Debian 12 LXC/VPS. Он предназначен прежде всего для разработчика и Codex: здесь зафиксированы реальные пути, сервисы, особенности Telegram-транспорта, резервного копирования и правила безопасного деплоя.

> Не коммитьте секреты. Пароли БД, Telegram-токены, SSH private keys, Restic password и содержимое `deploy/plesk/live.env` должны оставаться только на сервере.

## Текущий статус миграции

На момент подготовки этого документа новый Debian-сервер уже полностью настроен, код синхронизируется через GitHub, база импортирована и локальные HTTP-проверки успешны. До финального cutover старый Plesk остаётся источником актуальных пользовательских данных и запущенного Telegram-бота/cron. Перед сменой DNS нужно сделать последнюю синхронизацию БД и runtime-файлов, затем выключить старые фоновые процессы и включить их на новом сервере.

DNS и окончательный HTTPS/certbot для нового production-хоста выполняются только в момент cutover. Почтовый стек на новом сервере подготовлен, но SPF/DKIM/PTR нужно завершить и проверить после переключения DNS.

## Базовый стек

- ОС: Debian 12 (Bookworm), LXC на Proxmox.
- Project root: `/var/www/swpro`.
- Владелец файлов проекта: `swpro:swpro`.
- Nginx: системный пакет Debian.
- PHP: PHP 8.3 FPM/CLI, основной бинарник `/usr/bin/php8.3`.
- Composer установлен системно.
- MariaDB: 10.11.
- Python: системный Python 3.11, виртуальное окружение проекта — `/var/www/swpro/.venv`.
- Git remote: `https://github.com/VAtapin/max-app.git`, production branch — `main`.

Старый Plesk-путь `/var/www/vhosts/swpro.ru/httpdocs` больше не является production-путём нового сервера. Новый код и все server-side скрипты должны использовать `/var/www/swpro`.

## Конфигурация приложения

Единственный runtime env-файл пока сохраняется по историческому пути:

```text
/var/www/swpro/deploy/plesk/live.env
```

Несмотря на `plesk` в имени каталога, на новом Debian этот файл пока остаётся каноническим источником runtime-конфигурации. Не создавайте параллельные `.env`, `bot/.env` или `admin/app/config/local.php`.

`deploy/plesk/live.env` исключён из Git и не должен попадать в репозиторий.

## Git и deploy

Рабочая схема:

```text
локальная разработка -> git push -> GitHub main -> production git pull
```

Production-код руками на сервере не редактируется. Runtime uploads, private storage и env-файлы не являются частью Git.

Новый deploy-скрипт находится в репозитории:

```text
deploy/server/deploy.sh
```

Он рассчитан на `/var/www/swpro` и:

1. проверяет наличие Git checkout, env, PHP и Python venv;
2. выполняет прикладную часть деплоя от пользователя `swpro`;
3. применяет ещё не выполненные SQL-миграции через `deploy/plesk/migrate-db.sh`;
4. проверяет подключение к БД;
5. синхронизирует Docsify с AI knowledge index;
6. обновляет Python-зависимости из `bot/requirements.txt`;
7. проверяет синтаксис Python и PHP;
8. перезапускает `max-app-telegram.service` только если он уже был запущен;
9. после деплоя запускает `/usr/local/sbin/swpro-health.sh`, если он существует.

Важно: `deploy/server/deploy.sh` сам не должен быть источником `git pull`. Серверный wrapper/ручная команда сначала обновляет checkout, затем запускает deploy:

```bash
cd /var/www/swpro
runuser -u swpro -- git fetch origin
runuser -u swpro -- git pull --ff-only
/var/www/swpro/deploy/server/deploy.sh
```

`run-billing.php` специально не запускается при каждом deploy. Биллинг — плановая бизнес-задача cron, а не часть выкладки кода.

## Production-only Git exclusions

На production используется `.git/info/exclude`, чтобы runtime-файлы не засоряли `git status`. В частности, локально исключены:

```text
.venv/
bot/.venv/
.well-known/
storage/private/
uploads/
admin/uploads/
```

Это не заменяет `.gitignore` репозитория и не должно использоваться для скрытия отслеживаемых файлов.

## Nginx

Основной vhost:

```text
/etc/nginx/sites-available/swpro.ru
```

Включён через `sites-enabled`.

Document root:

```text
/var/www/swpro
```

PHP обслуживается отдельным сокетом:

```text
/run/php/php8.3-fpm-swpro.sock
```

Кэш браузера для CSS/JS/изображений намеренно не включён: проект находится в активной разработке, а клиентские изображения и фронтенд часто меняются. OPcache PHP используется, но HTTP browser cache не форсируется.

Глобальный performance-конфиг Nginx:

```text
/etc/nginx/conf.d/10-performance.conf
```

Там используется gzip и отключено раскрытие версии Nginx. Не дублируйте `gzip on;`, если он уже задан в `/etc/nginx/nginx.conf`.

Security headers, используемые для SWPro:

```text
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
```

HSTS добавляется только после окончательного перехода на HTTPS.

### Защищённые каталоги

Nginx не должен отдавать служебные/runtime-каталоги и секретные файлы. Важно: блокировать нужно `admin/app`, а не весь `/admin`, иначе административная панель станет недоступна.

Должны быть закрыты как минимум:

```text
/.git
/database
/deploy
/bot
/admin/app
/storage/private
.env
README.md
composer.json
composer.lock
*.ini
*.log
*.sql
```

`/.well-known/` должен оставаться доступным для ACME/Let's Encrypt.

## PHP-FPM

Отдельный pool:

```text
/etc/php/8.3/fpm/pool.d/swpro.conf
```

Основные текущие параметры:

```ini
[swpro]
user = swpro
group = swpro
listen = /run/php/php8.3-fpm-swpro.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
pm.max_requests = 500
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 120
```

Стандартный pool `www` отключён, потому что production SWPro использует собственный pool. Для phpMyAdmin создан отдельный FPM pool.

OPcache включён. На текущем размере проекта штатного лимита 128 MB и `max_accelerated_files=10000` достаточно. `validate_timestamps=On` оставлен намеренно, чтобы изменения после Git deploy подхватывались без обязательного restart FPM.

## MariaDB

База приложения:

```text
health_sales_system
```

Пользователь приложения:

```text
max_app
```

Пароль никогда не коммитится; он хранится в `deploy/plesk/live.env`.

Production config:

```text
/etc/mysql/mariadb.conf.d/60-swpro.cnf
```

Текущие параметры для VPS с 4 GB RAM:

```ini
[mysqld]
bind-address = 127.0.0.1
innodb_buffer_pool_size = 1G
max_connections = 50
thread_cache_size = 32
table_open_cache = 2000
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mariadb-slow.log
long_query_time = 1
log_slow_verbosity = query_plan
```

MariaDB должна слушать только localhost. Не открывайте порт 3306 наружу.

## phpMyAdmin

phpMyAdmin установлен только как административный инструмент и не публикуется в Интернет.

Nginx для phpMyAdmin слушает только:

```text
127.0.0.1:8081
```

Конфиг:

```text
/etc/nginx/sites-available/phpmyadmin
```

Отдельный FPM pool:

```text
/etc/php/8.3/fpm/pool.d/phpmyadmin.conf
```

Доступ выполняется через SSH client-to-server tunnel с рабочего компьютера, например локальный `127.0.0.1:8888 -> server 127.0.0.1:8081`. Не создавайте публичный `/phpmyadmin` или `db.swpro.ru` без отдельного решения по безопасности.

## SSH и безопасность

Административный Linux-пользователь:

```text
altarnik
```

Он входит по SSH key и имеет `sudo`. Прямой SSH login для root и парольная аутентификация отключены. Root остаётся доступен через Proxmox console как аварийный путь.

SSH hardening хранится в:

```text
/etc/ssh/sshd_config.d/99-hardening.conf
```

Ключевые установки:

```text
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
```

Firewall: nftables.

Основной конфиг:

```text
/etc/nftables.conf
```

Входящая политика — `drop`; разрешены loopback, established/related, ICMP, SSH 22, HTTP 80 и HTTPS 443. Временные WireGuard-правила не являются частью production-конфигурации и должны быть удалены.

Fail2ban включён для `sshd`:

```text
/etc/fail2ban/jail.d/sshd.local
```

Автоматические security updates включены через `unattended-upgrades` и `/etc/apt/apt.conf.d/20auto-upgrades`.

## Telegram: прозрачный SSH transport через Германию

Это критически важная особенность production в РФ.

Прямое соединение российского production-сервера с `api.telegram.org:443` таймаутится. Чтобы не переписывать десятки PHP/Python-функций и не добавлять proxy awareness в приложение, Telegram перенаправляется прозрачно на уровне ОС через отдельный SSH local forward на сервер в Германии.

Приложение по-прежнему обращается к обычному адресу:

```text
https://api.telegram.org/...
```

Никаких специальных URL, SOCKS-параметров или изменений aiogram/PHP-кода для production не требуется.

### Схема

```text
SWPro code / PHP / curl / aiogram
        |
        | https://api.telegram.org:443
        v
/etc/hosts: api.telegram.org -> 127.77.0.1
        |
        v
nftables OUTPUT NAT redirect 127.77.0.1:443 -> 127.0.0.1:18443
        |
        v
swpro-telegram-tunnel.service
SSH local forward via German relay
        |
        v
Germany -> api.telegram.org:443
```

### Сервис на production

```text
/etc/systemd/system/swpro-telegram-tunnel.service
```

Он держит SSH local forward:

```text
127.0.0.1:18443 -> api.telegram.org:443
```

через отдельного пользователя `swpro-telegram` на немецком relay-host. SSH key хранится вне репозитория в `/root/.ssh/swpro_telegram_ed25519`.

Сервис настроен с `Restart=always`, `ServerAliveInterval` и `ServerAliveCountMax`, поэтому туннель автоматически восстанавливается после разрыва.

### Локальное разрешение имени

В `/etc/hosts` production-сервера есть:

```text
127.77.0.1 api.telegram.org
```

Python `socket.getaddrinfo("api.telegram.org", 443)` на production должен возвращать `127.77.0.1`.

### nftables redirect

В `/etc/nftables.conf` есть отдельная NAT-таблица для Telegram:

```nft
table ip telegram_proxy {
    chain output {
        type nat hook output priority -100;
        policy accept;
        ip daddr 127.77.0.1 tcp dport 443 redirect to :18443
    }
}
```

### Проверка

```bash
systemctl is-active swpro-telegram-tunnel
ss -ltnp | grep 18443
getent ahostsv4 api.telegram.org
curl --connect-timeout 5 --max-time 10 https://api.telegram.org/
```

Для реальной Bot API проверки безопаснее использовать `getMe`, а не `getUpdates`:

```bash
curl --connect-timeout 5 --max-time 10 \
  "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getMe"
```

Основной бот SWPro работает через aiogram long polling (`dispatcher.start_polling`), поэтому тот же прозрачный HTTPS-туннель обеспечивает и получение updates, и отправку сообщений. Webhook для основного Telegram-бота не используется.

### German relay

На немецком сервере используется отдельный Linux-пользователь `swpro-telegram`. Для него разрешён только SSH local TCP forwarding, без shell/TTY/X11/agent forwarding. В `authorized_keys` ключ production-сервера рекомендуется ограничивать `from=...` и `permitopen="api.telegram.org:443"`.

Не используйте backup-пользователя для Telegram-туннеля.

## Telegram application service

Основной bot service на новом Debian:

```text
/etc/systemd/system/max-app-telegram.service
```

Рабочие параметры:

```text
User=swpro
Group=swpro
WorkingDirectory=/var/www/swpro
EnvironmentFile=/var/www/swpro/deploy/plesk/live.env
Environment=SWPRO_ENV_FILE=/var/www/swpro/deploy/plesk/live.env
ExecStart=/var/www/swpro/.venv/bin/python -m bot.telegram.main
```

До финального cutover сервис на новом сервере должен оставаться остановленным, чтобы не было двух polling-процессов с одним Telegram bot token. При переключении сначала остановить старый `max-app-telegram.service`, затем запустить новый.

## Cron приложения

На новом Debian cron выполняется от пользователя `swpro`, а Plesk-пути `/opt/plesk/php/...` больше не используются.

Текущие production-задачи:

```cron
MAILTO=""
SHELL="/bin/sh"

*/5 * * * * cd /var/www/swpro && /usr/bin/php8.3 admin/cron/run-broadcasts.php > /dev/null 2>&1
*/15 * * * * cd /var/www/swpro && /usr/bin/php8.3 admin/cron/run-automations.php > /dev/null 2>&1
15 0 * * * cd /var/www/swpro && /usr/bin/php8.3 admin/cron/run-billing.php >> storage/logs/billing-cron.log 2>&1
```

До cutover эти строки должны быть закомментированы на новом сервере, чтобы не было дублей со старым Plesk.

В репозитории также документирована очистка web-профилей через `admin/cron/cleanup-web-users.php`; перед cutover нужно сверить фактический crontab нового сервера и при необходимости добавить эту задачу отдельно.

## Backup: Restic -> немецкий сервер

Код не бэкапится как ежедневный архив: он восстанавливается из GitHub. Бэкапятся только данные и важные server-side конфиги.

Backup script:

```text
/usr/local/sbin/swpro-restic-backup.sh
```

Systemd units:

```text
/etc/systemd/system/swpro-restic-backup.service
/etc/systemd/system/swpro-restic-backup.timer
```

Репозиторий Restic расположен на удалённом немецком сервере через SFTP/SSH alias `swpro-backup-host`. Пример repository URL:

```text
sftp:swpro-backup-host:/var/backups/swpro-restic
```

Restic password file:

```text
/root/.config/restic/swpro.pass
```

Этот пароль обязательно должен иметь независимую копию вне обоих серверов. Без него encrypted Restic repository не восстановить.

База временно выгружается в `/run/swpro-database.sql.gz`, то есть не накапливает ежедневные архивы на небольшом SSD production-сервера. После backup временный dump удаляется.

Backup включает как минимум:

```text
/run/swpro-database.sql.gz
/var/www/swpro/admin/uploads
/var/www/swpro/uploads
/var/www/swpro/storage/private
/var/www/swpro/deploy/plesk/live.env
/etc/nginx/sites-available/swpro.ru
/etc/php/8.3/fpm/pool.d/swpro.conf
/etc/mysql/mariadb.conf.d/60-swpro.cnf
/etc/systemd/system/max-app-telegram.service
/etc/nftables.conf
/etc/fail2ban/jail.d/sshd.local
/etc/opendkim.conf
/etc/opendkim/keys/swpro.ru
```

Retention:

```text
7 daily
4 weekly
6 monthly
```

`restic forget` используется вместе с `--prune`, чтобы старые unreferenced blocks реально освобождали место на backup-сервере.

Таймер запускается ночью по Europe/Berlin с небольшим randomized delay. Проверенный restore является обязательной частью эксплуатации; тест восстановления отдельного файла уже выполнялся.

## Monitoring

 Конечно. Вот **готовый блок целиком** для замены старого `## Monitoring` в `docs/PRODUCTION_SERVER.md`.

````markdown
## Monitoring

На новом production-сервере мониторинг разделён на две независимые части:

1. `swpro-health.sh` — автоматическая проверка состояния сервера и отправка Telegram ALERT/RECOVERED;
2. `swpro-server-bot.py` — интерактивный Telegram-бот для ручного запроса состояния сервера.

Оба компонента используют отдельного серверного Telegram-бота и не являются частью основного `max-app-telegram.service`.

### Health check

Health script:

```text
/usr/local/sbin/swpro-health.sh
````

Systemd unit:

```text
/etc/systemd/system/swpro-health.service
```

Timer:

```text
/etc/systemd/system/swpro-health.timer
```

Проверка выполняется примерно каждые 5 минут.

Health check контролирует:

* nginx;
* PHP 8.3 FPM;
* MariaDB;
* SSH;
* Fail2ban;
* `swpro-telegram-tunnel`;
* `swpro-server-bot`;
* локальный HTTP response SWPro;
* внешний HTTPS response `https://swpro.ru`;
* DNS;
* SSL certificate;
* использование диска;
* inode;
* RAM;
* swap;
* load;
* состояние Restic backup timer;
* критические ошибки systemd/journal.

### Telegram monitoring configuration

Секреты и параметры серверного мониторинга хранятся только на production:

```text
/root/.config/swpro-monitoring.env
```

В частности:

```text
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...
```

Этот файл не должен попадать в Git.

`TELEGRAM_CHAT_ID` используется одновременно:

* для отправки health alerts;
* для ограничения доступа к server-monitor Telegram bot.

Telegram monitoring bot принимает команды только от настроенного `TELEGRAM_CHAT_ID`.

### Startup notification

После reboot health check определяет новый `/proc/sys/kernel/random/boot_id` и отправляет одно уведомление:

```text
🟢 SWPRO SERVER STARTED

Host: swpro.ru
Uptime: ...
Kernel: ...

Health check started.
```

Таким образом, после перезагрузки сервера можно сразу убедиться, что production снова запущен.

### Alert state and anti-spam

Health check не отправляет одно и то же уведомление каждые 5 минут.

Состояние проблем сравнивается по стабильным идентификаторам проблем, а не по полному тексту сообщения.

Например, изменение:

```text
Critical journal errors: 5
```

на:

```text
Critical journal errors: 2
```

не считается новой проблемой.

Таким образом, временные изменения количества записей в journal не создают Telegram-спам.

При появлении новой проблемы отправляется:

```text
🔴 SWPRO ALERT
```

После полного восстановления отправляется:

```text
🟢 SWPRO RECOVERED
```

### Journal monitoring

Количество критических journal errors используется только как признак наличия проблемы.

Изменение количества ошибок само по себе не создаёт новый ALERT.

Особенно важно после reboot: временные ошибки запуска Telegram transport или server-monitor bot не должны превращаться в бесконечный поток одинаковых уведомлений.

Состояние самого `swpro-server-bot` контролируется отдельно через systemd:

```text
swpro-server-bot.service
```

### Server Telegram bot

Отдельный серверный Telegram bot:

```text
/usr/local/sbin/swpro-server-bot.py
```

Systemd unit:

```text
/etc/systemd/system/swpro-server-bot.service
```

Основные свойства:

```text
Type=simple
User=root
Group=root
Restart=always
RestartSec=10
```

Сервис включён в автозапуск:

```bash
systemctl enable swpro-server-bot.service
```

Он не зависит от SSH-сессии администратора и автоматически запускается после reboot.

Сервис запускается после:

```text
network-online.target
swpro-telegram-tunnel.service
```

Однако Telegram tunnel может потребовать несколько секунд для фактического установления SSH forward. Поэтому сам bot polling также имеет retry/recovery и автоматически повторяет подключение при временной ошибке.

### Telegram server bot и основной application bot

`swpro-server-bot.py` является отдельным серверным инструментом.

Не путать:

```text
max-app-telegram.service
```

— основной Telegram-бот приложения SWPro,

и:

```text
swpro-server-bot.service
```

— отдельный Telegram-бот мониторинга сервера.

Server monitor не использует application code и не должен добавляться в `bot/` проекта.

### Server bot configuration

Server bot использует тот же:

```text
/root/.config/swpro-monitoring.env
```

и получает:

```text
TELEGRAM_BOT_TOKEN
TELEGRAM_CHAT_ID
```

Никакие Telegram token или chat ID не должны быть захардкожены в Python-коде.

### Telegram server bot commands

Доступные команды:

```text
/status
```

Общее состояние сервера и основных проверок.

```text
/server
```

Hostname, FQDN, IP, OS, kernel и uptime.

```text
/resources
```

Load, RAM и Swap.

```text
/disk
```

Использование диска и inode.

```text
/services
```

Состояние основных systemd-сервисов:

* nginx;
* PHP-FPM;
* MariaDB;
* SSH;
* Fail2ban;
* Telegram tunnel;
* server monitor bot;
* Restic timer.

```text
/website
```

DNS, внешний HTTPS, HTTP status и время ответа.

```text
/ssl
```

Состояние SSL certificate, срок действия и количество оставшихся дней.

```text
/backup
```

Состояние Restic timer и возраст последнего успешного backup.

```text
/logs
```

Последние критические ошибки systemd/journal.

```text
/all
```

Полный отчёт по серверу, включающий:

* общий status;
* server information;
* resources;
* disk/inode;
* services;
* website/DNS/HTTPS;
* SSL;
* Restic;
* critical logs.

### `/all` и сетевые проверки

Каждая внешняя проверка в `/all` выполняется независимо.

Недоступный HTTPS или SSL не должен прерывать формирование всего отчёта.

Например, если HTTPS временно недоступен:

```text
🌐 WEBSITE

HTTPS: 🔴 connection failed
```

и:

```text
🔐 SSL

Status: 🔴 unavailable
```

остальные разделы `/all` всё равно должны быть сформированы и отправлены.

Индивидуальные внешние проверки используют короткий timeout. Telegram long polling использует отдельный более длинный timeout, поскольку `getUpdates` работает с long polling.

### Telegram long polling

Server monitor bot использует Telegram Bot API `getUpdates`.

Текущий polling timeout:

```text
50 seconds
```

HTTP timeout для Telegram API:

```text
70 seconds
```

Это необходимо, чтобы HTTP-соединение не завершалось раньше, чем Telegram завершит long-polling запрос.

Временные ошибки подключения обрабатываются внутри polling loop; бот автоматически повторяет подключение.

### Telegram transport

Server monitoring bot использует тот же прозрачный Telegram transport, который описан выше в разделе:

```text
Telegram: прозрачный SSH transport через Германию
```

Application code и server monitoring bot обращаются к стандартному:

```text
https://api.telegram.org/...
```

Proxy URL или специальные Telegram API endpoints в Python-коде не используются.

Таким образом, существующий:

```text
swpro-telegram-tunnel.service
```

обеспечивает как отправку сообщений server monitor, так и получение команд через `getUpdates`.

### Проверка monitoring

Health check:

```bash
/usr/local/sbin/swpro-health.sh
```

Systemd:

```bash
systemctl status swpro-health.service --no-pager
systemctl status swpro-health.timer --no-pager
systemctl list-timers --all | grep swpro-health
```

Последний health journal:

```bash
journalctl -u swpro-health.service -n 100 --no-pager
```

Server monitor bot:

```bash
systemctl status swpro-server-bot.service --no-pager
```

Лог server monitor bot:

```bash
journalctl -u swpro-server-bot.service -n 100 --no-pager
```

Проверка автозапуска:

```bash
systemctl is-enabled swpro-health.timer
systemctl is-enabled swpro-server-bot.service
```

Проверка Telegram transport:

```bash
systemctl is-active swpro-telegram-tunnel
ss -ltnp | grep 18443
getent ahostsv4 api.telegram.org
```

Проверка Bot API:

```bash
curl --connect-timeout 5 --max-time 10 \
  "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getMe"
```

### Важное замечание

Если одновременно недоступен сам `swpro-telegram-tunnel`, server health check может корректно обнаружить проблему, но Telegram ALERT с российского production-сервера в этот момент может не доставиться.

Это ограничение самого канала уведомлений.

Сам `swpro-server-bot` и `swpro-telegram-tunnel` автоматически восстанавливаются после временных сетевых ошибок.

Состояние сервера после reboot дополнительно подтверждается уведомлением `SWPRO SERVER STARTED`.

 

## Logrotate

Логи SWPro ротируются через:

```text
/etc/logrotate.d/swpro
```

Runtime logs в `/var/www/swpro/storage/logs/*.log` ротируются ежедневно, хранятся ограниченное число дней и сжимаются. Используется `su swpro swpro` и `copytruncate`.

## Почта

PHP-восстановление пароля и часть application email используют обычный PHP `mail()`. На новом сервере настроен локальный Postfix, который слушает только loopback; полноценный входящий mail server для пользовательских ящиков здесь не поднимается.

OpenDKIM установлен. Новый selector:

```text
swpro2026
```

Private DKIM key хранится только на сервере:

```text
/etc/opendkim/keys/swpro.ru/swpro2026.private
```

Postfix подключён к OpenDKIM через `smtpd_milters` и `non_smtpd_milters` на `127.0.0.1:8891`.

До cutover не считать почту полностью завершённой. После переключения DNS требуется проверить/добавить:

- DKIM TXT для `swpro2026._domainkey`;
- SPF с новым отправляющим IP;
- PTR / reverse DNS;
- `mail.swpro.ru`, если используется в HELO/PTR;
- DMARC alignment;
- тестовое письмо с проверкой `SPF=PASS`, `DKIM=PASS`, `DMARC=PASS`.

Не менять почтовые DNS преждевременно, пока старый сервер ещё обслуживает production.

## Cutover checklist

Перед окончательной сменой DNS:

1. убедиться, что `main` одинаков на локальной машине, GitHub и новом сервере;
2. остановить/закомментировать cron SWPro на старом сервере;
3. остановить старый `max-app-telegram.service`;
4. сделать финальный свежий dump `health_sales_system`;
5. синхронизировать только runtime-данные (`admin/uploads`, `uploads`, `storage/private` и иные реальные пользовательские файлы), не код;
6. импортировать финальную БД на новый сервер;
7. проверить локально сайт и административные страницы через Host header;
8. проверить Telegram SSH tunnel и Bot API `getMe`;
9. переключить DNS на новый production;
10. получить/установить HTTPS certificate и сделать redirect HTTP -> HTTPS;
11. включить cron нового сервера;
12. запустить `max-app-telegram.service` на новом сервере;
13. завершить SPF/DKIM/PTR/DMARC для нового исходящего mail;
14. проверить Mini App, публичные страницы, админку, платежи, рассылки, password reset, Telegram polling/sendMessage и фоновые задачи;
15. старый сервер не удалять сразу — оставить как fallback на несколько дней, но не запускать на нём фоновые процессы, создающие дубль.

## Диагностика

Основные команды:

```bash
systemctl is-active nginx php8.3-fpm mariadb fail2ban swpro-telegram-tunnel
systemctl list-timers --all | grep swpro
journalctl -u swpro-telegram-tunnel -n 100 --no-pager
journalctl -u max-app-telegram.service -n 100 --no-pager
journalctl -u swpro-restic-backup.service -n 100 --no-pager
journalctl -u swpro-health.service -n 100 --no-pager
nginx -t
php-fpm8.3 -t
mariadb -e 'SELECT VERSION();'
ss -tulpn
nft list ruleset
```

Git production state:

```bash
runuser -u swpro -- git -C /var/www/swpro status --short --branch
runuser -u swpro -- git -C /var/www/swpro remote -v
```

Telegram transport:

```bash
systemctl status swpro-telegram-tunnel --no-pager
ss -ltnp | grep 18443
getent ahostsv4 api.telegram.org
python3 - <<'PY'
import socket
for row in socket.getaddrinfo('api.telegram.org', 443):
    print(row)
PY
```

Ожидаемый адрес `api.telegram.org` на production — `127.77.0.1`; это намеренная локальная подмена для прозрачного SSH transport.

## Правила для Codex и разработчика

- Не возвращать Plesk-пути в новый production-код. Новый root — `/var/www/swpro`.
- Не переносить Python venv обратно в `bot/.venv`; production venv — `/var/www/swpro/.venv`.
- Не добавлять proxy/alternate Telegram API URL в сотни функций: прозрачный transport уже решён на уровне ОС.
- Не менять `api.telegram.org` в application code на локальный адрес. Код должен использовать официальный hostname.
- Не коммитить `live.env`, private keys, Restic password, Telegram tokens или DB passwords.
- Не запускать billing как побочный эффект deploy.
- Не запускать второй Telegram polling process до остановки старого.
- Не кэшировать персонализированные PHP-страницы через FastCGI cache без отдельного анализа — риск утечки персонализированных данных между пользователями.
- Пока проект активно разрабатывается, не добавлять агрессивный browser cache для CSS/JS/клиентских изображений без versioned/hash URLs.
- При изменении server architecture обновлять этот документ и соответствующий раздел `README.md`.