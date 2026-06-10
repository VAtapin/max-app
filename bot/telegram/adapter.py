from aiogram import Bot, Dispatcher

from bot.telegram.handlers.common import router


def build_dispatcher() -> Dispatcher:
    dispatcher = Dispatcher()
    dispatcher.include_router(router)
    return dispatcher


def build_bot(token: str) -> Bot:
    return Bot(token=token)
