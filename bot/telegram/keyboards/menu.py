from aiogram.types import KeyboardButton, ReplyKeyboardMarkup

from bot.core.messages import MAIN_MENU


def main_menu_keyboard() -> ReplyKeyboardMarkup:
    rows = [
        [KeyboardButton(text=MAIN_MENU[0]), KeyboardButton(text=MAIN_MENU[1])],
        [KeyboardButton(text=MAIN_MENU[2]), KeyboardButton(text=MAIN_MENU[3])],
        [KeyboardButton(text=MAIN_MENU[4]), KeyboardButton(text=MAIN_MENU[5])],
    ]
    return ReplyKeyboardMarkup(keyboard=rows, resize_keyboard=True)
