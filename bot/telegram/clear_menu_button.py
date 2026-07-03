import asyncio
import os

from aiogram.types import MenuButtonDefault

from bot.core.environment import load_project_env
from bot.telegram.adapter import build_bot


async def main() -> None:
    env_file = load_project_env()

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError(f"TELEGRAM_BOT_TOKEN is not set in {env_file}")

    bot = build_bot(token)
    try:
        await bot.set_chat_menu_button(menu_button=MenuButtonDefault())
        print("Telegram menu button reset to default.")
    finally:
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
