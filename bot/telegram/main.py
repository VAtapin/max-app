import asyncio
import logging
import os
from pathlib import Path

from dotenv import load_dotenv

from bot.db.mysql import close_pool, init_pool
from bot.telegram.adapter import build_bot, build_dispatcher


async def main() -> None:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")
    logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO"))

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError("TELEGRAM_BOT_TOKEN is not set")

    await init_pool()
    bot = build_bot(token)
    dispatcher = build_dispatcher()

    try:
        me = await bot.get_me()
        logging.info("Starting Telegram polling for @%s (%s)", me.username, me.id)
        await bot.delete_webhook(drop_pending_updates=False)
        await dispatcher.start_polling(bot, allowed_updates=dispatcher.resolve_used_update_types())
    finally:
        await bot.session.close()
        await close_pool()


if __name__ == "__main__":
    asyncio.run(main())
