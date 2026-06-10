import asyncio
import os
from pathlib import Path

from aiogram.types import MenuButtonWebApp, WebAppInfo
from dotenv import load_dotenv

from bot.telegram.adapter import build_bot


async def main() -> None:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError("TELEGRAM_BOT_TOKEN is not set in bot/.env")

    mini_app_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/mini-app/index.html")
    bot = build_bot(token)
    try:
        await bot.set_chat_menu_button(
            menu_button=MenuButtonWebApp(
                text="Открыть SWPro",
                web_app=WebAppInfo(url=mini_app_url),
            )
        )
        print(f"Telegram menu button set: {mini_app_url}")
    finally:
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
