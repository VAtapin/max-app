import os

from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup, WebAppInfo

from bot.core.i18n import tr


def mini_app_keyboard() -> InlineKeyboardMarkup:
    mini_app_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/mini-app/index.html")
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(
                    text=tr("menu.open_swpro"),
                    web_app=WebAppInfo(url=mini_app_url),
                )
            ],
            [
                InlineKeyboardButton(text=tr("menu.catalog"), callback_data="products"),
                InlineKeyboardButton(text=tr("menu.recommendations"), callback_data="recommendations"),
            ],
            [
                InlineKeyboardButton(text=tr("menu.contact_manager"), callback_data="contact_manager"),
            ],
        ]
    )
