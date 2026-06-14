from __future__ import annotations

import os
from urllib.parse import urlencode

from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup, WebAppInfo

from bot.core.i18n import tr


def button_text(value: object, limit: int = 58) -> str:
    text = " ".join(str(value or "").split())
    if len(text) <= limit:
        return text
    return text[: limit - 1].rstrip() + "..."


def mini_app_url(referral_code: str | None = None, page: str | None = None, test_id: int | None = None) -> str:
    base_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/mini-app/index.html").strip()
    params: dict[str, str | int] = {}
    if referral_code:
        params["ref"] = referral_code
    if page:
        params["page"] = page
    if test_id:
        params["test_id"] = test_id

    if not params:
        return base_url

    separator = "&" if "?" in base_url else "?"
    return f"{base_url}{separator}{urlencode(params)}"


def app_button(referral_code: str | None = None, page: str | None = None, test_id: int | None = None) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(
                    text=tr("menu.open_swpro"),
                    web_app=WebAppInfo(url=mini_app_url(referral_code, page, test_id)),
                )
            ]
        ]
    )


def tests_keyboard(tests: list[dict]) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text=button_text(item["title"]), callback_data=f"test:start:{item['id']}")]
            for item in tests[:20]
        ]
    )


def materials_keyboard(materials: list[dict]) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text=button_text(item["title"]), callback_data=f"material:open:{item['id']}")]
            for item in materials[:20]
        ]
    )


def answers_keyboard(question: dict, selected: set[int] | None = None) -> InlineKeyboardMarkup:
    selected = selected or set()
    rows = []
    for answer in question.get("answers", []):
        prefix = "[x] " if int(answer["id"]) in selected else ""
        action = "multi" if question["question_type"] == "multiple_choice" else "answer"
        rows.append([
            InlineKeyboardButton(
                text=button_text(f"{prefix}{answer['answer_text']}"),
                callback_data=f"test:{action}:{answer['id']}",
            )
        ])

    if question["question_type"] == "multiple_choice":
        rows.append([InlineKeyboardButton(text=tr("tests.answer_done"), callback_data="test:done")])

    return InlineKeyboardMarkup(inline_keyboard=rows)
