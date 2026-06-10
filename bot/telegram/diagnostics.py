import asyncio
import os
from pathlib import Path

from dotenv import load_dotenv

from bot.db.mysql import close_pool, cursor, init_pool
from bot.telegram.adapter import build_bot


async def main() -> None:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError("TELEGRAM_BOT_TOKEN is not set in bot/.env")

    bot = build_bot(token)
    try:
        me = await bot.get_me()
        webhook = await bot.get_webhook_info()
        print(f"Telegram bot: @{me.username} id={me.id}")
        print(f"Webhook url: {webhook.url or '(empty)'}")
        print(f"Pending updates: {webhook.pending_update_count}")

        await init_pool()
        async with cursor() as cur:
            await cur.execute("SELECT COUNT(*) AS total FROM admin_users")
            row = await cur.fetchone()
        print(f"Database: ok, admin_users={row['total']}")
    finally:
        await bot.session.close()
        await close_pool()


if __name__ == "__main__":
    asyncio.run(main())
