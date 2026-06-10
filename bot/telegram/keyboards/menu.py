import os

from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup, WebAppInfo


def mini_app_keyboard() -> InlineKeyboardMarkup:
    mini_app_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/mini-app/index.html")
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(
                    text="Открыть SWPro",
                    web_app=WebAppInfo(url=mini_app_url),
                )
            ],
            [
                InlineKeyboardButton(text="Каталог", callback_data="products"),
                InlineKeyboardButton(text="Рекомендации", callback_data="recommendations"),
            ],
            [
                InlineKeyboardButton(text="Связаться с менеджером", callback_data="contact_manager"),
            ],
        ]
    )
