import asyncio
import logging
import os

from aiogram.types import BotCommand, MenuButtonCommands

from bot.core.environment import load_project_env
from bot.db.mysql import close_pool, init_pool
from bot.telegram.adapter import build_bot, build_dispatcher


async def configure_telegram_menu(bot) -> None:
    await bot.set_my_commands(
        [
            BotCommand(command="start", description="Начать работу"),
            BotCommand(command="menu", description="Главное меню"),
            BotCommand(command="app", description="Открыть SWPro"),
            BotCommand(command="tests", description="Чек-ап организма"),
            BotCommand(command="manager", description="Связаться с консультантом"),
            BotCommand(command="privacy", description="Документы и согласия"),
            BotCommand(command="unsubscribe", description="Отключить рассылки"),
            BotCommand(command="revoke", description="Отозвать согласия"),
            BotCommand(command="help", description="Помощь"),
        ]
    )
    await bot.set_chat_menu_button(menu_button=MenuButtonCommands())


async def main() -> None:
    load_project_env()
    logging.basicConfig(level=os.getenv("LOG_LEVEL", "INFO"))

    token = os.getenv("TELEGRAM_BOT_TOKEN")
    if not token:
        raise RuntimeError("TELEGRAM_BOT_TOKEN is not set")

    await init_pool()
    bot = build_bot(token)
    dispatcher = build_dispatcher()

    try:
        me = await bot.get_me()
        await configure_telegram_menu(bot)
        logging.info("Starting Telegram polling for @%s (%s)", me.username, me.id)
        await bot.delete_webhook(drop_pending_updates=False)
        await dispatcher.start_polling(bot, allowed_updates=dispatcher.resolve_used_update_types())
    finally:
        await bot.session.close()
        await close_pool()


if __name__ == "__main__":
    asyncio.run(main())
