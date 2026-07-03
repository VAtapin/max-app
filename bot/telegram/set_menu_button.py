import asyncio
import os

from bot.core.environment import load_project_env
from bot.telegram.adapter import build_bot
from bot.telegram.main import configure_telegram_menu


async def main() -> None:
    env_file = load_project_env()

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError(f"TELEGRAM_BOT_TOKEN is not set in {env_file}")

    bot = build_bot(token)
    try:
        await configure_telegram_menu(bot)
        print("Telegram command menu configured.")
    finally:
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
