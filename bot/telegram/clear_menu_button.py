import asyncio
import os
from pathlib import Path

from aiogram.types import MenuButtonDefault
from dotenv import load_dotenv

from bot.telegram.adapter import build_bot


async def main() -> None:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError("TELEGRAM_BOT_TOKEN is not set in bot/.env")

    bot = build_bot(token)
    try:
        await bot.set_chat_menu_button(menu_button=MenuButtonDefault())
        print("Telegram menu button reset to default.")
    finally:
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
