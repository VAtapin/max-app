# Health Sales Support

Каркас мультиканальной системы для сети продаж товаров для здоровья.

Состав проекта:

- `admin/` - PHP 8 админ-панель без фреймворков.
- `api/` - общий JSON API для VK Mini App и будущих интеграций.
- `bot/` - Telegram Bot, MAX Bot и общая Python-бизнес-логика.
- `vk-mini-app/` - статическое VK Mini App на HTML/CSS/JavaScript.
- `database/` - SQL-схема и стартовые данные.

## Требования

- PHP 8+
- MySQL 8 или MariaDB 10+
- Python 3.12+
- Веб-сервер Apache или Nginx с PHP

Laravel, Symfony, CMS и ORM-фреймворки не используются.

## Установка базы данных

Создайте базу и таблицы:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

Тестовый супер-админ:

- Email: `admin@example.com`
- Password: `admin123`
- Role: `superadmin`

Пароль в `seed.sql` хранится как bcrypt hash, совместимый с `password_verify`.

## Настройка PHP

Параметры подключения к базе находятся в:

```text
admin/app/config/config.php
```

По умолчанию используются:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=health_sales_system
DB_USERNAME=root
DB_PASSWORD=
```

Можно переопределить эти значения через переменные окружения.

Публичная директория админки:

```text
admin/public
```

Стартовая страница:

```text
/admin/public/login.php
```

## Python bots

Создайте окружение и установите зависимости:

```bash
python -m venv bot\.venv
bot\.venv\Scripts\activate
pip install -r bot\requirements.txt
copy bot\.env.example bot\.env
```

Заполните `.env`:

```text
TELEGRAM_BOT_TOKEN=...
MAX_BOT_TOKEN=...
DB_HOST=127.0.0.1
DB_NAME=health_sales_system
DB_USER=root
DB_PASSWORD=...
```

Запуск Telegram Bot:

```bash
python -m bot.telegram.main
```

MAX Bot сейчас подготовлен как адаптер и обработчик общей логики. Для production нужно добавить webhook/server loop под правила MAX Bot API:

```bash
python -m bot.max.main
```

## VK Mini App

Файлы находятся в:

```text
vk-mini-app/
```

Приложение использует VK Bridge и общий API:

```text
api/auth.php
api/user.php
api/products.php
api/tests.php
api/recommendations.php
api/contact_manager.php
```

Для локальной разработки можно открыть `vk-mini-app/index.html` через веб-сервер проекта. API должен быть доступен по соседнему пути `../api`.

## Важные правила MVP

- MVP не является интернет-магазином.
- Нет корзины, заказов, оплат, склада и доставки.
- Интерес пользователя фиксируется через `leads`.
- Первичная реферальная привязка постоянная: повторный вход по другой ссылке не меняет менеджера и реселлера.
- `manager_id` у пользователя может быть `NULL`, если регистрация пришла по ссылке реселлера.
- Изображения продуктов и контента хранятся в файловой системе, в БД хранится только путь.
- Telegram, MAX и VK используют общую БД и общую структуру пользователей, продуктов, тестов и рекомендаций.

## Медицинский дисклеймер

Информация носит ознакомительный характер и не является медицинской рекомендацией. Перед применением продуктов проконсультируйтесь со специалистом.

В текстах нельзя обещать лечение заболеваний или замену лекарств.
