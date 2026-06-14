import asyncio
import os
from pathlib import Path

from dotenv import load_dotenv

from bot.telegram.adapter import build_bot
from bot.telegram.main import configure_telegram_menu


async def main() -> None:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError("TELEGRAM_BOT_TOKEN is not set in bot/.env")

    bot = build_bot(token)
    try:
        await configure_telegram_menu(bot)
        print("Telegram command menu configured.")
    finally:
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
