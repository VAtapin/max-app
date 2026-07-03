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
    base_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/vk-mini-app/").strip()
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


def main_menu_keyboard(referral_code: str | None = None, diagnosis_test_id: int | None = None) -> InlineKeyboardMarkup:
    rows: list[list[InlineKeyboardButton]] = []
    if diagnosis_test_id:
        rows.append([
            InlineKeyboardButton(
                text="🌿 Чек-ап организма",
                callback_data=f"test:start:{diagnosis_test_id}",
            )
        ])
    rows.extend([
        [
            InlineKeyboardButton(text="🎁 Кэшбэк и подарки", callback_data="section:cashback")
        ],
        [
            InlineKeyboardButton(text="📌 Связаться с консультантом", callback_data="lead:contact"),
        ],
        [
            InlineKeyboardButton(text="🤝 Возможность сотрудничества", callback_data="section:cooperation")
        ],
    ])
    return InlineKeyboardMarkup(inline_keyboard=rows)


def result_actions_keyboard(referral_code: str | None = None) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text="📌 Разобрать с консультантом", callback_data="lead:contact")],
            [InlineKeyboardButton(text="📋 Главное меню", callback_data="menu:main")],
        ]
    )


def consent_keyboard(public_url: str, step: str) -> InlineKeyboardMarkup:
    if step == "personal":
        return InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="Политика данных", url=f"{public_url}/legal.php?type=privacy_policy")],
            [InlineKeyboardButton(text="Согласие на обработку данных", url=f"{public_url}/legal.php?type=personal_data_consent")],
            [InlineKeyboardButton(text="Пользовательское соглашение", url=f"{public_url}/legal.php?type=user_agreement")],
            [InlineKeyboardButton(text="✅ Принимаю условия", callback_data="onboarding:accept:personal")],
            [InlineKeyboardButton(text="Не согласен", callback_data="onboarding:decline")],
        ])
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="Согласие на данные чек-апа", url=f"{public_url}/legal.php?type=health_data_consent")],
        [InlineKeyboardButton(text="✅ Даю согласие", callback_data="onboarding:accept:health")],
        [InlineKeyboardButton(text="Не согласен", callback_data="onboarding:decline")],
    ])


def use_profile_value_keyboard(value: str | None, action: str, *, allow_skip: bool = False) -> InlineKeyboardMarkup | None:
    rows: list[list[InlineKeyboardButton]] = []
    if value:
        rows.append([InlineKeyboardButton(text=f"Оставить: {button_text(value, 36)}", callback_data=f"onboarding:use:{action}")])
    if allow_skip:
        rows.append([InlineKeyboardButton(text="Пропустить", callback_data=f"onboarding:skip:{action}")])
    return InlineKeyboardMarkup(inline_keyboard=rows) if rows else None


def gender_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="Женщина", callback_data="onboarding:gender:female")],
        [InlineKeyboardButton(text="Мужчина", callback_data="onboarding:gender:male")],
        [InlineKeyboardButton(text="Не хочу указывать", callback_data="onboarding:gender:prefer_not_to_say")],
    ])


def marketing_keyboard(public_url: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="Согласие на рассылку", url=f"{public_url}/legal.php?type=marketing_consent")],
        [InlineKeyboardButton(text="Да, получать полезные сообщения", callback_data="onboarding:marketing:yes")],
        [InlineKeyboardButton(text="Нет, только сервисные сообщения", callback_data="onboarding:marketing:no")],
    ])


def resume_test_keyboard(test_id: int) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text="Продолжить с прошлого вопроса", callback_data=f"test:resume:{test_id}")],
            [InlineKeyboardButton(text="Начать заново", callback_data=f"test:restart:{test_id}")],
        ]
    )


def completed_test_keyboard(test_id: int) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text="Посмотреть результат", callback_data=f"test:result:{test_id}")],
            [InlineKeyboardButton(text="Пройти заново", callback_data=f"test:restart:{test_id}")],
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
