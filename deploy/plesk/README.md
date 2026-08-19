# Рабочий деплой SWPro на Plesk для swpro.ru

Целевой сервер:

- Plesk Obsidian на Ubuntu 22.04
- Домен: `swpro.ru`
- Корень сайта: `/var/www/vhosts/swpro.ru/httpdocs`
- PHP: 8.3.x, PHP-FPM
- База данных: MariaDB 10.6.x

## 1. Подготовка домена

В Plesk установите корень сайта домена:

```text
httpdocs
```

Включите PHP-FPM с PHP 8.3.

Лимиты загрузки задаются в PHP/Plesk. Пример значений для изображений, PDF и видео:

```text
upload_max_filesize = 300M
post_max_size = 320M
```

Рекомендуемые расширения PHP:

```text
pdo_mysql
mbstring
json
openssl
curl
fileinfo
```

## 2. Загрузка кода

В каталоге `/var/www/vhosts/swpro.ru/httpdocs`:

```bash
git clone https://github.com/VAtapin/max-app.git .
```

Если каталог не пустой, загрузите файлы или выполните pull в существующей копии Git.

## 3. Создание базы данных в Plesk

Создайте в Plesk базу MariaDB и пользователя, например:

```text
База: health_sales_system
Пользователь: max_app
Пароль: надёжный пароль
```

MariaDB 10.6 достаточно для текущей схемы.

## 4. Настройка live.env

```bash
cd /var/www/vhosts/swpro.ru/httpdocs
cp deploy/plesk/env.example deploy/plesk/live.env
nano deploy/plesk/live.env
```

Заполните реквизиты БД, токен Telegram, публичные URL, системного пользователя и
`SWPRO_PRIVATE_STORAGE_PATH`. Приватный каталог должен находиться за пределами
`httpdocs`, например `/var/www/vhosts/swpro.ru/private/swpro`; в нём хранятся
исходные файлы аватаров и сгенерированные материалы, которые нельзя отдавать
публично. Это единственный рабочий файл конфигурации для PHP-приложения, ботов,
миграций и systemd-сервиса. Не создавайте `admin/app/config/local.php`,
`bot/.env` или корневой `.env`.

Для функций ИИ укажите `OPENAI_API_KEY` для генерации текста и голоса. Для
настоящих ИИ-видео дополнительно нужен `HEYGEN_API_KEY` или `TAVUS_API_KEY`; после
деплоя выберите того же провайдера в настройках ИИ супер-администратора.
Сгенерированные MP3 и MP4 сохраняются в `SWPRO_PRIVATE_STORAGE_PATH`.

## Аварийное восстановление на старом Plesk-сервере

Новый рабочий сервер записывает зашифрованные снимки Restic на старый
немецкий сервер по SFTP. Сам репозиторий находится на старом сервере в
`/var/backups/swpro-restic`. Пароль Restic должен быть доступен в
`/root/.config/restic/swpro.pass` и не должен попадать в Git.

На старом сервере вспомогательный скрипт устанавливается как
`/usr/local/sbin/swpro-restore-from-restic.sh`. Он восстанавливает только базу
данных приложения и рабочие файлы (`admin/uploads`, `uploads` и приватное
хранилище). Конфигурация Debian Nginx/PHP-FPM поверх Plesk намеренно не
восстанавливается.

Старый Telegram-бот и задания Plesk должны оставаться остановленными во время
восстановления. Скрипт сначала создаёт локальную резервную копию текущего
состояния и требует явного подтверждения. Все команды выполняются от `root` на
старом немецком сервере:

```bash
# Проверка (версия Restic должна быть не ниже 0.14.0)
restic version
test -r /root/.config/restic/swpro.pass
test -d /var/backups/swpro-restic

# Установка или обновление вспомогательного скрипта после актуализации копии
install -o root -g root -m 0750 \
  /var/www/vhosts/swpro.ru/httpdocs/deploy/plesk/restore-from-restic.sh \
  /usr/local/sbin/swpro-restore-from-restic.sh

# Проверка и подготовка без изменения рабочего сайта
swpro-restore-from-restic.sh list
swpro-restore-from-restic.sh verify latest
swpro-restore-from-restic.sh stage latest
swpro-restore-from-restic.sh apply latest --confirm
```

Команда `apply` восстанавливает базу автоматически — вручную выполнять `mysql`
не нужно. Скрипт берёт параметры подключения из старого
`deploy/plesk/live.env`, создаёт резервную копию текущей базы и файлов в
`/var/backups/swpro-pre-restore-<UTC timestamp>`, импортирует сжатый дамп через
`mariadb` и синхронизирует три рабочих каталога. Дамп базы внутри снимка
находится в `/run/swpro-database.sql.gz`.

Во временный каталог также попадает `live.env` нового сервера, но только для справки. Он
не должен заменять Plesk-файл окружения. Если старый `live.env` отсутствует или
его реквизиты базы не работают, `apply` запускать нельзя: сначала нужно
исправить конфигурацию. Не создавайте новую базу и не выполняйте ручной импорт
в обход вспомогательного скрипта.

Флаг `--deploy` добавляется только после проверки временной копии, если старый
Plesk-сайт нужно подготовить существующим скриптом деплоя. DNS автоматически не
меняется, а Telegram-бот после восстановления остаётся остановленным.

Для Telegram OpenID Connect на обычном сайте в браузере компьютера или телефона:

- укажите `TELEGRAM_OIDC_CLIENT_ID` и `TELEGRAM_OIDC_CLIENT_SECRET`;
- зарегистрируйте `https://swpro.ru/api/telegram_oidc_callback.php` как URL перенаправления;
- оставьте `SWPRO_PUBLIC_URL=https://swpro.ru`;
- оставьте `SWPRO_MINI_APP_URL=https://swpro.ru/vk-mini-app/`.

Для VK:

- `VK_APP_ID` и `VK_SECURE_KEY` используются для авторизации VK Mini App.
- `VK_GROUP_TOKEN` нужен для отправки ответов пользователям VK из админки. Создайте
  его в настройках сообщества VK: ключи доступа API с разрешением на сообщения
  сообщества.
- Одного `VK_SERVICE_TOKEN` недостаточно для `messages.send`: VK отклоняет этот
  метод для сервисных токенов.

Общего системного пользователя Plesk можно проверить командой:

```bash
stat -c '%U %G' /var/www/vhosts/swpro.ru/httpdocs
```

Укажите полученные значения в `APP_USER` и `APP_GROUP`.

## 5. Установка

```bash
cd /var/www/vhosts/swpro.ru/httpdocs
bash deploy/plesk/install.sh deploy/plesk/live.env
```

Скрипт создаёт `bot/.venv`, публичные каталоги загрузок и приватный каталог
медиа ИИ, проверяет подключение к БД и удаляет устаревшие копии конфигурации.
Приватный `deploy/plesk/live.env` игнорируется Git. Telegram Mini App читает
токен бота из этого файла для проверки `initData`.

## 6. Импорт базы данных

```bash
bash deploy/plesk/import-db.sh deploy/plesk/live.env
```

Чистый импорт загружает текущую схему, демонстрационные данные, чек-ап из 47
вопросов и метаданные его медиафайлов.

Администратор по умолчанию после импорта:

```text
Email: admin@example.com
Пароль: admin123
```

Измените пароль после первого входа.

Для последующих обновлений схемы без удаления данных запускайте миграции, а не
повторный полный импорт. Выполненные миграции отслеживаются и повторно не
запускаются:

```bash
bash deploy/plesk/migrate-db.sh deploy/plesk/live.env
```

## 7. Защита приватных путей

В репозитории есть `.htaccess`. Для Plesk/nginx также добавьте:

- `deploy/plesk/apache-additional.conf` — в Additional Apache directives;
- `deploy/plesk/nginx-additional.conf` — в Additional nginx directives.

После этого перезапустите Apache/nginx из Plesk.

Файлы аватаров выдаются только через PHP-обработчик, доступный авторизованному
владельцу. Встроенные правила веб-сервера также запрещают доступ к
`/storage/private`, если резервный каталог когда-либо окажется внутри проекта.

Добавьте ежедневное задание планировщика с периодами хранения из раздела
`Настройки → Настройки ИИ`:

```bash
php /var/www/vhosts/swpro.ru/httpdocs/admin/cron/cleanup-ai-data.php
```

Используйте `--dry-run`, чтобы посмотреть количество записей без удаления.

Стандартный деплой также запускает `admin/cron/sync-docsify-ai.php` после
миграций, поэтому страницы Docsify из Git становятся доступными для поиска
админ-помощником без отдельного ручного копирования.

## 8. Сервис Telegram-бота

Установите сгенерированный из `live.env` сервис:

```bash
sudo bash deploy/plesk/install-systemd.sh deploy/plesk/live.env
sudo systemctl status max-app-telegram.service
```

Логи:

```bash
journalctl -u max-app-telegram.service -f
```

Диагностика Telegram:

```bash
bash deploy/plesk/check-telegram.sh
```

Установить кнопку меню Telegram-бота:

```bash
bot/.venv/bin/python -m bot.telegram.set_menu_button
```

Сбросить пользовательскую кнопку меню, если BotFather уже предоставляет кнопку
открытия Mini App:

```bash
bot/.venv/bin/python -m bot.telegram.clear_menu_button
```

## 9. Обновление и деплой

Для последующих обновлений:

```bash
cd /var/www/vhosts/swpro.ru/httpdocs
bash deploy/plesk/deploy.sh deploy/plesk/live.env
```

Скрипт получает новый код, применяет миграции, проверяет синтаксис, обновляет
зависимости бота и перезапускает сервис бота, если он установлен.

## 10. Планировщик заданий

Создайте четыре задания Plesk от имени системного пользователя сайта:

```cron
*/5 * * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/run-broadcasts.php
*/15 * * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/run-automations.php
15 0 * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/run-billing.php >> storage/logs/billing-cron.log 2>&1
35 0 * * * cd /var/www/vhosts/swpro.ru/httpdocs && php admin/cron/cleanup-web-users.php >> storage/logs/web-user-cleanup.log 2>&1
```

Второе задание отправляет напоминания о незавершённых чек-апах через 24 часа,
3 дня и 7 дней, а сообщения о неактивности — через 14 и 30 дней. Отправка
ограничена дневным временем в часовом поясе клиента. Третье задание создаёт
счета за предыдущий календарный месяц и обновляет доступ конкретного рабочего
места после окончания срока оплаты. Четвёртое задание удаляет заброшенные
временные web-профили после настроенного периода в 3 или 5 дней.

## 11. Быстрая проверка URL

```text
https://swpro.ru/api/index.php
https://swpro.ru/admin/public/login.php
https://swpro.ru/vk-mini-app/
https://swpro.ru/docs/
https://swpro.ru/legal.php?type=privacy_policy
```

Проверка API:

```bash
curl -s https://swpro.ru/api/index.php
```
