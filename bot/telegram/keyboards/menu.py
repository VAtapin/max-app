import os

from aiogram.types import KeyboardButton, ReplyKeyboardMarkup, WebAppInfo

from bot.core.messages import MAIN_MENU


def main_menu_keyboard() -> ReplyKeyboardMarkup:
    mini_app_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/mini-app/index.html")
    rows = [
        [KeyboardButton(text="Открыть SWPro", web_app=WebAppInfo(url=mini_app_url))],
        [KeyboardButton(text=MAIN_MENU[0]), KeyboardButton(text=MAIN_MENU[1])],
        [KeyboardButton(text=MAIN_MENU[2]), KeyboardButton(text=MAIN_MENU[3])],
        [KeyboardButton(text=MAIN_MENU[4]), KeyboardButton(text=MAIN_MENU[5])],
    ]
    return ReplyKeyboardMarkup(keyboard=rows, resize_keyboard=True)
