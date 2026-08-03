from __future__ import annotations

import html
import os
from pathlib import Path
from uuid import uuid4

from aiogram import F, Router
from aiogram.filters import BaseFilter, Command
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import CallbackQuery, InlineKeyboardButton, InlineKeyboardMarkup, Message, ReplyKeyboardRemove, User

from bot.core.i18n import tr
from bot.core.client_journey import (
    complete_onboarding,
    consultant_profile_for_user,
    create_consultant_notification,
    grant_consent,
    mark_consultant_notification_delivery,
    onboarding_status,
    parse_age_or_birth_date,
    revoke_consents,
)
from bot.core.consultant_replies import (
    consultant_reply_context,
    create_telegram_lead_response,
    finish_telegram_lead_response,
    telegram_client_chat_id,
    telegram_reply_already_saved,
)
from bot.core.leads import create_lead
from bot.core.materials import get_material, list_materials
from bot.core.products import list_products
from bot.core.recommendations import list_recommendations
from bot.core.tests import (
    complete_test_session,
    get_or_create_test_session,
    get_test,
    latest_completed_test_result,
    latest_draft_test_session,
    list_tests,
    save_session_answer,
    save_test_result,
    session_answered_question_ids,
)
from bot.core.users import (
    StaffAccountError,
    account_suggestions_payload,
    get_or_create_user,
    parse_account_link_token,
)
from bot.telegram.keyboards.menu import (
    app_button,
    answers_keyboard,
    completed_test_keyboard,
    consent_keyboard,
    gender_keyboard,
    main_menu_keyboard,
    marketing_keyboard,
    materials_keyboard,
    mini_app_url,
    result_actions_keyboard,
    resume_test_keyboard,
    tests_keyboard,
    use_profile_value_keyboard,
)

router = Router()


class TestFlow(StatesGroup):
    answering = State()


class LeadFlow(StatesGroup):
    waiting_message = State()


class ReferralFlow(StatesGroup):
    waiting_code = State()


class OnboardingFlow(StatesGroup):
    waiting_first_name = State()
    waiting_last_name = State()
    waiting_age = State()
    waiting_city = State()
    waiting_marketing = State()


class ConsultantNotificationReplyFilter(BaseFilter):
    async def __call__(self, message: Message) -> dict | bool:
        if (
            message.chat.type != "private"
            or message.reply_to_message is None
            or message.from_user is None
        ):
            return False

        context = await consultant_reply_context(
            str(message.chat.id),
            int(message.reply_to_message.message_id),
        )
        return {"consultant_notification": context} if context else False


def consultant_reply_attachment(message: Message) -> tuple[str, str] | None:
    if message.photo:
        return message.photo[-1].file_id, "jpg"
    if message.video:
        return message.video.file_id, "mp4"
    if message.document:
        suffix = Path(message.document.file_name or "").suffix.lower()
        allowed = {
            ".jpg": "jpg",
            ".jpeg": "jpg",
            ".png": "png",
            ".webp": "webp",
            ".pdf": "pdf",
            ".mp4": "mp4",
        }
        if suffix in allowed:
            return message.document.file_id, allowed[suffix]
    return None


async def save_consultant_reply_attachment(message: Message) -> list[str]:
    attachment = consultant_reply_attachment(message)
    if not attachment:
        return []

    file_id, extension = attachment
    directory = Path(__file__).resolve().parents[3] / "admin" / "uploads" / "responses"
    directory.mkdir(parents=True, exist_ok=True)
    filename = f"{uuid4().hex}.{extension}"
    await message.bot.download(file_id, destination=directory / filename)
    return [f"/admin/uploads/responses/{filename}"]


@router.message(ConsultantNotificationReplyFilter())
async def consultant_notification_reply(
    message: Message,
    consultant_notification: dict,
) -> None:
    telegram_chat_id = str(message.chat.id)
    telegram_message_id = int(message.message_id)
    if await telegram_reply_already_saved(telegram_chat_id, telegram_message_id):
        await message.answer("Этот ответ уже обработан.")
        return

    message_text = (message.text or message.caption or "").strip()
    has_supported_attachment = consultant_reply_attachment(message) is not None
    if message.document and not has_supported_attachment:
        await message.answer("Поддерживаются документы JPG, PNG, WEBP, PDF и MP4.")
        return
    if not message_text and not has_supported_attachment:
        await message.answer("Ответьте текстом, фотографией, видео MP4 или документом PDF.")
        return

    try:
        attachment_paths = await save_consultant_reply_attachment(message)
    except Exception as exc:
        await message.answer(f"Не удалось сохранить вложение: {str(exc)[:300]}")
        return

    response_id, lead_id, created = await create_telegram_lead_response(
        consultant_notification,
        telegram_chat_id,
        telegram_message_id,
        message_text,
        attachment_paths,
    )
    if not created:
        await message.answer("Этот ответ уже обработан.")
        return

    source_platform = str(
        consultant_notification.get("lead_source_platform")
        or consultant_notification.get("source_platform")
        or "web"
    )
    delivery_ok = True
    delivery_error = None
    if source_platform == "telegram":
        client_chat_id = await telegram_client_chat_id(
            int(consultant_notification["end_user_id"])
        )
        if not client_chat_id:
            delivery_ok = False
            delivery_error = "Telegram клиента не подключён"
        else:
            try:
                await message.bot.copy_message(
                    chat_id=client_chat_id,
                    from_chat_id=message.chat.id,
                    message_id=message.message_id,
                )
            except Exception as exc:
                delivery_ok = False
                delivery_error = str(exc)[:500]

    await finish_telegram_lead_response(
        response_id,
        lead_id,
        consultant_notification,
        delivery_ok,
        delivery_error,
        message_text,
    )
    if delivery_ok:
        destination = (
            "Telegram"
            if source_platform == "telegram"
            else f"{platform_display_name(source_platform)} Mini App"
        )
        await message.answer(f"Ответ отправлен клиенту: {destination}.")
    else:
        await message.answer(
            "Ответ сохранён, но доставить его не удалось. "
            f"Причина: {delivery_error or 'неизвестная ошибка'}."
        )


async def resolve_user(message: Message, referral_code: str | None = None, link_token: str | None = None) -> dict:
    return await resolve_telegram_user(message.from_user, referral_code, link_token)


async def resolve_telegram_user(
    tg_user: User | None,
    referral_code: str | None = None,
    link_token: str | None = None,
) -> dict:
    if tg_user is None:
        raise RuntimeError("Telegram user is missing")

    return await get_or_create_user(
        platform="telegram",
        platform_user_id=str(tg_user.id),
        username=tg_user.username,
        first_name=tg_user.first_name,
        last_name=tg_user.last_name,
        referral_code=referral_code,
        link_token=link_token,
    )


def start_payload(text: str | None) -> str | None:
    parts = (text or "").split(maxsplit=1)
    return parts[1].strip() if len(parts) > 1 else None


def link_token_from_start(text: str | None) -> str | None:
    payload = start_payload(text)
    if payload and (payload.startswith("link_") or payload.startswith("l_")):
        return payload
    return None


def referral_from_start(text: str | None) -> str | None:
    payload = start_payload(text)
    if payload and not (payload.startswith("link_") or payload.startswith("l_")):
        return payload
    return None


def user_referral_code(user: dict) -> str | None:
    return user.get("referral_code_used")


def has_consultant_binding(user: dict) -> bool:
    return bool(user.get("manager_id") or user.get("reseller_id"))


def platform_display_name(platform: str | None) -> str:
    return {
        "telegram": "Telegram",
        "VK": "VK",
        "OK": "OK",
        "MAX": "MAX",
        "web": "Web",
    }.get(str(platform or ""), str(platform or "Web"))


async def request_referral_code(message: Message, state: FSMContext, *, invalid: bool = False) -> None:
    await state.set_state(ReferralFlow.waiting_code)
    text = (
        "Код не найден или консультант неактивен. Проверьте код и отправьте его ещё раз."
        if invalid
        else "Откройте персональную ссылку консультанта или отправьте его реферальный код сообщением."
    )
    await message.answer(text, reply_markup=ReplyKeyboardRemove())


def diagnosis_test_id(tests: list[dict]) -> int | None:
    for item in tests:
        title = str(item.get("title") or "").lower()
        if "диагност" in title:
            return int(item["id"])
    return int(tests[0]["id"]) if tests else None


def public_base_url() -> str:
    return os.getenv("SWPRO_PUBLIC_URL", "https://swpro.ru").rstrip("/")


async def show_main_menu(message: Message, user: dict) -> None:
    tests = await list_tests()
    await message.answer(
        "Выберите нужный раздел:",
        reply_markup=main_menu_keyboard(user_referral_code(user), diagnosis_test_id(tests)),
    )


async def send_account_link_suggestion(message: Message, user: dict) -> None:
    checked_user = dict(user)
    checked_user["current_platform"] = checked_user.get("current_platform") or "telegram"
    payload = await account_suggestions_payload(checked_user)
    suggestions = payload.get("suggestions") or []
    if not suggestions:
        return

    links = (payload.get("linking") or {}).get("links") or {}
    platforms = ", ".join(str(item.get("platform_label") or item.get("platform") or "") for item in suggestions)
    suggestion_platforms = {str(item.get("platform") or "") for item in suggestions}
    rows: list[list[InlineKeyboardButton]] = []
    if "VK" in suggestion_platforms and links.get("vk"):
        rows.append([InlineKeyboardButton(text="Подтвердить в VK", url=str(links["vk"]))])
    if "telegram" in suggestion_platforms and links.get("telegram"):
        rows.append([InlineKeyboardButton(text="Подтвердить в Telegram", url=str(links["telegram"]))])
    if links.get("mini_app"):
        rows.append([InlineKeyboardButton(text="Открыть Mini App", url=str(links["mini_app"]))])
    rows.append([InlineKeyboardButton(text="Не сейчас", callback_data="account_link:dismiss")])

    await message.answer(
        (
            "Похоже, у вас уже есть профиль на другой платформе: "
            f"{html.escape(platforms)}.\n\n"
            "Если это действительно ваш аккаунт, откройте его по кнопке ниже и подтвердите вход. "
            "Автоматически ничего не объединяется."
        ),
        parse_mode="HTML",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=rows),
    )


async def send_consultant_welcome(message: Message, user: dict) -> None:
    profile = await consultant_profile_for_user(user)
    consultant_name = (profile or {}).get("display_name") or "вашего консультанта"
    welcome_text = (profile or {}).get("welcome_text") or (profile or {}).get("short_description")
    text = (
        f"Здравствуйте! Это бот консультанта <b>{html.escape(str(consultant_name))}</b>.\n\n"
        f"{html.escape(str(welcome_text or 'Здесь вы сможете пройти бесплатный чек-ап организма, узнать о кэшбэке и связаться с консультантом.'))}"
    )
    image_url = public_url((profile or {}).get("welcome_image_path") or (profile or {}).get("photo_path"))
    if image_url:
        try:
            await message.answer_photo(image_url, caption=text, parse_mode="HTML")
        except Exception:
            await message.answer(text, parse_mode="HTML")
    else:
        await message.answer(text, parse_mode="HTML")

    video_url = str((profile or {}).get("welcome_video_url") or "").strip()
    if video_url:
        try:
            if video_url.lower().split("?", 1)[0].endswith(".mp4"):
                await message.answer_video(video_url)
            else:
                await message.answer(f"Приветственное видео:\n{video_url}")
        except Exception:
            await message.answer(f"Приветственное видео:\n{video_url}")


async def start_profile_questionnaire(message: Message, state: FSMContext, user: dict) -> None:
    await state.set_state(OnboardingFlow.waiting_first_name)
    await state.update_data(
        onboarding_user_id=int(user["id"]),
        onboarding_user=user,
        profile_first_name=str(user.get("first_name") or ""),
        profile_last_name=str(user.get("last_name") or ""),
    )
    await message.answer(
        "Как вас зовут? Напишите имя или подтвердите имя из Telegram.",
        reply_markup=use_profile_value_keyboard(str(user.get("first_name") or ""), "first_name"),
    )


async def continue_onboarding(message: Message, state: FSMContext, user: dict) -> bool:
    if not has_consultant_binding(user):
        await request_referral_code(message, state)
        return False

    status = await onboarding_status(user)
    missing = set(status["missing_consents"])
    if "personal_data_consent" in missing or "user_agreement" in missing:
        await message.answer(
            "Перед началом ознакомьтесь с документами и подтвердите согласие на обработку персональных данных.",
            reply_markup=consent_keyboard(public_base_url(), "personal"),
        )
        return False
    if "health_data_consent" in missing:
        await message.answer(
            "Чек-ап содержит вопросы о самочувствии. Для его проведения нужно отдельное согласие.",
            reply_markup=consent_keyboard(public_base_url(), "health"),
        )
        return False
    if not status["complete"]:
        await start_profile_questionnaire(message, state, user)
        return False
    return True


async def notify_manager_event(
    bot,
    user: dict,
    *,
    notification_type: str,
    event_key: str,
    title: str,
    message_text: str,
    lead_id: int | None = None,
    source_platform: str | None = None,
) -> None:
    notification = await create_consultant_notification(
        user,
        notification_type,
        event_key,
        title,
        message_text,
        lead_id=lead_id,
        source_platform=source_platform,
    )
    if not notification:
        return

    notification_id = int(notification["notification_id"])
    chat_id = str(notification.get("telegram_id") or "").strip()
    if not chat_id:
        await mark_consultant_notification_delivery(
            notification_id,
            False,
            "Telegram ID не указан",
        )
        return

    action_path = (
        f"/admin/public/crud.php?module=leads&action=edit&id={lead_id}"
        if lead_id
        else "/admin/public/results.php"
    )
    reply_markup = InlineKeyboardMarkup(
        inline_keyboard=[[
            InlineKeyboardButton(
                text="Открыть в админке",
                url=f"{public_base_url()}{action_path}",
            )
        ]]
    )
    try:
        sent = await bot.send_message(chat_id, message_text, reply_markup=reply_markup)
        await mark_consultant_notification_delivery(
            notification_id,
            True,
            telegram_chat_id=str(sent.chat.id),
            telegram_message_id=int(sent.message_id),
        )
    except Exception as exc:
        await mark_consultant_notification_delivery(
            notification_id,
            False,
            str(exc)[:500],
            telegram_chat_id=chat_id,
        )


def consultant_contacts_text(profile: dict | None) -> str:
    if not profile:
        return "Контакты консультанта пока не заполнены."
    lines = ["<b>Контакты консультанта</b>"]
    for label, field in (
        ("Телефон", "phone"),
        ("Email", "email"),
        ("Telegram", "telegram_url"),
        ("WhatsApp", "whatsapp_url"),
        ("VK", "vk_url"),
        ("OK", "ok_url"),
    ):
        value = str(profile.get(field) or "").strip()
        if value:
            lines.append(f"{label}: {html.escape(value)}")
    return "\n".join(lines)


async def send_profile_section(message: Message, user: dict, section: str) -> None:
    profile = await consultant_profile_for_user(user)
    if section == "cashback":
        title = (profile or {}).get("cashback_title") or "Кэшбэк и подарки"
        text = (profile or {}).get("cashback_text") or "Консультант расскажет, как оформить карту клиента и получать доступные преимущества."
        image_path = (profile or {}).get("cashback_image_path")
        link = (profile or {}).get("cashback_url")
    else:
        title = (profile or {}).get("cooperation_title") or "Возможность сотрудничества"
        text = (profile or {}).get("cooperation_text") or "Узнайте о вариантах сотрудничества и задайте вопросы консультанту."
        image_path = (profile or {}).get("cooperation_image_path")
        link = None
    video_url = str((profile or {}).get("cooperation_video_url") or "").strip() if section == "cooperation" else ""

    body = f"<b>{html.escape(str(title))}</b>\n\n{html.escape(str(text))}"
    if link:
        body += f"\n\nОформить карту клиента: {html.escape(str(link))}"
    image_url = public_url(image_path)
    if image_url:
        try:
            await message.answer_photo(image_url, caption=body, parse_mode="HTML")
        except Exception:
            await message.answer(body, parse_mode="HTML")
    else:
        await message.answer(body, parse_mode="HTML")
    if video_url:
        try:
            if video_url.lower().split("?", 1)[0].endswith(".mp4"):
                await message.answer_video(video_url)
            else:
                await message.answer(f"Видео о сотрудничестве:\n{video_url}")
        except Exception:
            await message.answer(f"Видео о сотрудничестве:\n{video_url}")


def progress_bar(index: int, total: int, width: int = 10) -> str:
    if total <= 0:
        return ""
    filled = max(0, min(width, round((index / total) * width)))
    return "●" * filled + "○" * (width - filled)


async def delete_message_silently(message: Message | None) -> None:
    if not message:
        return
    try:
        await message.delete()
    except Exception:
        pass


async def delete_message_by_id_silently(message: Message, message_id: int | None) -> None:
    if not message_id:
        return
    try:
        await message.bot.delete_message(chat_id=message.chat.id, message_id=message_id)
    except Exception:
        pass


async def deliver_test_message(
    message: Message,
    state: FSMContext,
    text: str,
    *,
    reply_markup=None,
    replace_current: bool = False,
) -> None:
    if replace_current:
        try:
            await message.edit_text(text, reply_markup=reply_markup, parse_mode="HTML")
            return
        except Exception:
            pass

    sent = await message.answer(text, reply_markup=reply_markup, parse_mode="HTML")
    await state.update_data(current_question_message_id=sent.message_id)


def format_material(item: dict) -> str:
    parts = [f"<b>{html.escape(str(item['title']))}</b>"]
    text = item.get("full_text") or item.get("short_text")
    if text:
        parts.append(html.escape(str(text)))
    if item.get("video_url"):
        parts.append(f"Видео: {html.escape(str(item['video_url']))}")
    if item.get("button_url"):
        label = item.get("button_text") or "Ссылка"
        parts.append(f"{html.escape(str(label))}: {html.escape(str(item['button_url']))}")
    return "\n\n".join(parts)


def public_url(path: str | None) -> str | None:
    value = str(path or "").strip()
    if not value:
        return None
    if value.startswith(("http://", "https://")):
        return value
    base_url = os.getenv("SWPRO_PUBLIC_URL", "https://swpro.ru").rstrip("/")
    return f"{base_url}/{value.lstrip('/')}"


async def send_material(message: Message, item: dict) -> None:
    await message.answer(format_material(item), parse_mode="HTML")

    image_url = public_url(item.get("image_path"))
    if image_url:
        await message.answer_photo(image_url)

    attachment_url = public_url(item.get("attachment_path"))
    if attachment_url:
        lower = attachment_url.lower()
        if lower.endswith((".jpg", ".jpeg", ".png", ".webp")):
            await message.answer_photo(attachment_url)
        elif lower.endswith(".mp4"):
            await message.answer_video(attachment_url)
        else:
            await message.answer_document(attachment_url)


def format_recommendation(item: dict) -> str:
    title = html.escape(str(item.get("product_title") or tr("recommendations.default")))
    parts = [f"<b>{title}</b>"]
    if item.get("short_description"):
        parts.append(html.escape(str(item["short_description"])))
    if item.get("reason_text"):
        parts.append(html.escape(str(item["reason_text"])))
    return "\n".join(parts)


async def send_test_result_message(
    message: Message,
    user: dict,
    result: dict,
    *,
    replace_current: bool = False,
) -> None:
    score_line = "" if result.get("scale_results") else f"\n\nБаллы: {result['total_score']}"
    scale_lines = []
    for item in result.get("scale_results") or []:
        scale_result = item.get("result") or {}
        result_title = scale_result.get("title") or "результат не задан"
        scale_lines.append(
            f"• {html.escape(str(item.get('title') or 'Шкала'))}: "
            f"<b>{html.escape(str(result_title))}</b> ({int(item.get('score') or 0)})"
        )
    scale_text = "\n\n<b>Карта по направлениям</b>\n" + "\n".join(scale_lines) if scale_lines else ""
    text = (
        f"<b>{html.escape(str(result['title']))}</b>"
        f"{score_line}\n\n"
        f"{html.escape(str(result['summary']))}"
        f"{scale_text}"
    )
    if replace_current:
        try:
            await message.edit_text(
                text,
                reply_markup=result_actions_keyboard(user_referral_code(user)),
                parse_mode="HTML",
            )
        except Exception:
            await message.answer(
                text,
                reply_markup=result_actions_keyboard(user_referral_code(user)),
                parse_mode="HTML",
            )
    else:
        await message.answer(
            text,
            reply_markup=result_actions_keyboard(user_referral_code(user)),
            parse_mode="HTML",
        )

async def send_test_question(message: Message, state: FSMContext, *, replace_current: bool = False) -> None:
    data = await state.get_data()
    questions = data["questions"]
    index = int(data["index"])

    if index >= len(questions):
        if data.get("session_id"):
            result = await complete_test_session(data["end_user_id"], data["test_id"], int(data["session_id"]))
        else:
            result = await save_test_result(data["end_user_id"], data["test_id"], data["answers"])
        user = data.get("user", {})
        client_name = " ".join(
            part for part in [str(user.get("first_name") or "").strip(), str(user.get("last_name") or "").strip()]
            if part
        )
        if not client_name:
            client_name = f"Клиент #{data['end_user_id']}"
        manager_scale_lines = []
        for item in result.get("scale_results") or []:
            scale_result = item.get("result") or {}
            manager_scale_lines.append(
                f"• {item.get('title') or 'Шкала'}: "
                f"{scale_result.get('title') or 'результат не задан'} "
                f"({int(item.get('score') or 0)})"
            )
        source_platform = str(user.get("current_platform") or user.get("platform") or "telegram")
        manager_parts = [
            "Завершён чек-ап",
            f"Источник: {platform_display_name(source_platform)}",
            f"Клиент: {client_name}",
            str(result.get("summary") or "").strip(),
        ]
        if manager_scale_lines:
            manager_parts.append("Карта по направлениям:\n" + "\n".join(manager_scale_lines))
        manager_parts.append(
            "Полный результат доступен в кабинете SWPro.\n\n"
            "Ответьте на это сообщение, чтобы написать клиенту."
        )
        await notify_manager_event(
            message.bot,
            user,
            notification_type="test_completed",
            event_key=f"test_completed:{result['session_id']}",
            title="Клиент завершил чек-ап",
            message_text="\n\n".join(part for part in manager_parts if part)[:3900],
            source_platform=source_platform,
        )
        await state.clear()
        await send_test_result_message(message, user, result, replace_current=replace_current)
        return

    question = questions[index]
    await state.update_data(current_selected=[])
    number = index + 1
    total = len(questions)
    bar = progress_bar(index, total)
    text = (
        f"<b>Вопрос {number} из {total}</b>\n"
        f"{bar} {index}/{total}\n\n"
        f"{html.escape(str(question['question_text']))}"
    )

    if question["question_type"] == "text" or not question.get("answers"):
        await deliver_test_message(
            message,
            state,
            text + "\n\n" + html.escape(tr("tests.text_answer_hint")),
            replace_current=replace_current,
        )
        return

    await deliver_test_message(
        message,
        state,
        text,
        reply_markup=answers_keyboard(question),
        replace_current=replace_current,
    )


async def start_test(
    message: Message,
    state: FSMContext,
    user: dict,
    test_id: int,
    *,
    reset: bool = False,
    force_resume: bool = False,
) -> None:
    test = await get_test(test_id, user.get("gender"))
    if not test or not test.get("questions"):
        await message.answer(tr("tests.no_questions"))
        return

    if not reset and not force_resume:
        draft = await latest_draft_test_session(user["id"], test["id"])
        if draft:
            answered_ids = await session_answered_question_ids(int(draft["id"]))
            if answered_ids:
                await message.answer(
                    "У вас есть незавершенный тест. Продолжить с прошлого вопроса или начать заново?",
                    reply_markup=resume_test_keyboard(test["id"]),
                )
                return

        completed = await latest_completed_test_result(user["id"], test["id"])
        if completed:
            await message.answer(
                "Этот тест уже пройден вами. Можно посмотреть результат или пройти тест заново.",
                reply_markup=completed_test_keyboard(test["id"]),
            )
            return

    session = await get_or_create_test_session(user["id"], test["id"], reset=reset)
    answered_ids = await session_answered_question_ids(int(session["id"]))

    start_index = 0
    for item_index, question in enumerate(test["questions"]):
        if int(question["id"]) not in answered_ids:
            start_index = item_index
            break
    else:
        start_index = len(test["questions"])

    await state.set_state(TestFlow.answering)
    await state.update_data(
        user=user,
        end_user_id=user["id"],
        test_id=test["id"],
        session_id=session["id"],
        questions=test["questions"],
        index=start_index,
        answers=[],
        current_selected=[],
        current_question_message_id=None,
    )
    intro = (test.get("intro_text") or test.get("description") or "").strip()
    emoji = test.get("emoji") or "🌿"
    await message.answer(
        (
            f"{html.escape(str(emoji))} <b>{html.escape(str(test['title']))}</b>\n\n"
            f"{html.escape(intro)}\n\n"
            f"Вопросов: {len(test['questions'])}. Отвечайте честно: так результат будет полезнее."
        ),
        parse_mode="HTML",
    )
    await send_test_question(message, state)


@router.message(Command("start"))
async def start(message: Message, state: FSMContext) -> None:
    await state.clear()
    referral_code = referral_from_start(message.text)
    link_token = link_token_from_start(message.text)
    try:
        user = await resolve_user(message, referral_code, link_token)
    except StaffAccountError:
        await message.answer(tr("staff.client_registration_blocked"), reply_markup=ReplyKeyboardRemove())
        return

    if not has_consultant_binding(user):
        await request_referral_code(message, state, invalid=bool(referral_code))
        return

    await send_consultant_welcome(message, user)
    link_target_id = parse_account_link_token(link_token)
    if link_target_id and int(user["id"]) == link_target_id:
        await message.answer("Telegram подключён к вашему профилю SWPro.")
    if await continue_onboarding(message, state, user):
        await show_main_menu(message, user)


@router.message(ReferralFlow.waiting_code)
async def referral_code_message(message: Message, state: FSMContext) -> None:
    referral_code = (message.text or "").strip()
    if not referral_code:
        await request_referral_code(message, state)
        return

    try:
        user = await resolve_user(message, referral_code)
    except StaffAccountError:
        await state.clear()
        await message.answer(tr("staff.client_registration_blocked"), reply_markup=ReplyKeyboardRemove())
        return

    if not has_consultant_binding(user):
        await request_referral_code(message, state, invalid=True)
        return

    await state.clear()
    await send_consultant_welcome(message, user)
    if await continue_onboarding(message, state, user):
        await show_main_menu(message, user)


@router.callback_query(F.data == "onboarding:accept:personal")
async def onboarding_accept_personal(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    await grant_consent(int(user["id"]), "personal_data_consent", "telegram")
    await grant_consent(int(user["id"]), "user_agreement", "telegram")
    if callback.message:
        await callback.message.answer(
            "Спасибо. Теперь подтвердите отдельное согласие на обработку ответов чек-апа.",
            reply_markup=consent_keyboard(public_base_url(), "health"),
        )
    await callback.answer()


@router.callback_query(F.data == "onboarding:accept:health")
async def onboarding_accept_health(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    await grant_consent(int(user["id"]), "health_data_consent", "telegram")
    if callback.message:
        await start_profile_questionnaire(callback.message, state, user)
    await callback.answer()


@router.callback_query(F.data == "onboarding:decline")
async def onboarding_decline(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    await revoke_consents(int(user["id"]))
    await state.clear()
    if callback.message:
        await callback.message.answer(
            "Без согласия на обработку данных анкета и чек-ап недоступны. Вернуться к оформлению можно командой /start."
        )
    await callback.answer()


@router.callback_query(F.data == "account_link:dismiss")
async def account_link_dismiss(callback: CallbackQuery) -> None:
    if callback.message:
        await callback.message.edit_reply_markup(reply_markup=None)
    await callback.answer("Хорошо")


async def ask_last_name(message: Message, state: FSMContext, value: str | None = None) -> None:
    await state.set_state(OnboardingFlow.waiting_last_name)
    await message.answer(
        "Укажите фамилию или подтвердите фамилию из Telegram.",
        reply_markup=use_profile_value_keyboard(value, "last_name"),
    )


async def ask_gender(message: Message, state: FSMContext) -> None:
    await message.answer("Укажите пол:", reply_markup=gender_keyboard())


@router.callback_query(F.data == "onboarding:use:first_name")
async def onboarding_use_first_name(callback: CallbackQuery, state: FSMContext) -> None:
    data = await state.get_data()
    value = str(data.get("profile_first_name") or callback.from_user.first_name or "").strip()
    if not value:
        await callback.answer("Напишите имя сообщением", show_alert=True)
        return
    await state.update_data(profile_first_name=value)
    if callback.message:
        await ask_last_name(callback.message, state, str(data.get("profile_last_name") or ""))
    await callback.answer()


@router.message(OnboardingFlow.waiting_first_name)
async def onboarding_first_name(message: Message, state: FSMContext) -> None:
    value = (message.text or "").strip()
    if len(value) < 2:
        await message.answer("Напишите имя полностью.")
        return
    await state.update_data(profile_first_name=value)
    data = await state.get_data()
    await ask_last_name(message, state, str(data.get("profile_last_name") or ""))


@router.callback_query(F.data == "onboarding:use:last_name")
async def onboarding_use_last_name(callback: CallbackQuery, state: FSMContext) -> None:
    data = await state.get_data()
    value = str(data.get("profile_last_name") or callback.from_user.last_name or "").strip()
    if not value:
        await callback.answer("Напишите фамилию сообщением", show_alert=True)
        return
    await state.update_data(profile_last_name=value)
    if callback.message:
        await ask_gender(callback.message, state)
    await callback.answer()


@router.callback_query(F.data == "onboarding:skip:last_name")
async def onboarding_skip_last_name(callback: CallbackQuery, state: FSMContext) -> None:
    if callback.message:
        await ask_last_name(callback.message, state)
    await callback.answer("Фамилия обязательна", show_alert=True)


@router.message(OnboardingFlow.waiting_last_name)
async def onboarding_last_name(message: Message, state: FSMContext) -> None:
    value = (message.text or "").strip()
    if len(value) < 2:
        await message.answer("Напишите фамилию полностью.")
        return
    await state.update_data(profile_last_name=value)
    await ask_gender(message, state)


@router.callback_query(F.data.startswith("onboarding:gender:"))
async def onboarding_gender(callback: CallbackQuery, state: FSMContext) -> None:
    gender = (callback.data or "").split(":")[-1]
    if gender not in {"female", "male", "prefer_not_to_say"}:
        await callback.answer("Неизвестный вариант", show_alert=True)
        return
    await state.update_data(profile_gender=gender)
    await state.set_state(OnboardingFlow.waiting_age)
    if callback.message:
        await callback.message.answer(
            "Укажите ваш возраст числом или дату рождения в формате ДД.ММ.ГГГГ."
        )
    await callback.answer()


@router.message(OnboardingFlow.waiting_age)
async def onboarding_age(message: Message, state: FSMContext) -> None:
    try:
        birth_date, age_years = parse_age_or_birth_date(message.text or "")
    except ValueError:
        await message.answer("Укажите возраст от 14 до 100 лет или дату в формате ДД.ММ.ГГГГ.")
        return
    await state.update_data(
        profile_birth_date=birth_date.isoformat() if birth_date else None,
        profile_age_years=age_years,
    )
    await state.set_state(OnboardingFlow.waiting_city)
    await message.answer("В каком городе вы живёте?")


@router.message(OnboardingFlow.waiting_city)
async def onboarding_city(message: Message, state: FSMContext) -> None:
    city = (message.text or "").strip()
    if len(city) < 2:
        await message.answer("Напишите название города.")
        return
    await state.update_data(profile_city=city)
    await state.set_state(OnboardingFlow.waiting_marketing)
    await message.answer(
        "Хотите получать полезные материалы, новости об акциях, подарках и программах? Это необязательно.",
        reply_markup=marketing_keyboard(public_base_url()),
    )


@router.callback_query(F.data.startswith("onboarding:marketing:"))
async def onboarding_marketing(callback: CallbackQuery, state: FSMContext) -> None:
    choice = (callback.data or "").split(":")[-1]
    data = await state.get_data()
    user = await resolve_telegram_user(callback.from_user)
    if choice == "yes":
        await grant_consent(int(user["id"]), "marketing_consent", "telegram")
    elif choice == "no":
        await revoke_consents(int(user["id"]), marketing_only=True)
    else:
        await callback.answer("Неизвестный вариант", show_alert=True)
        return

    birth_date = None
    if data.get("profile_birth_date"):
        birth_date, _ = parse_age_or_birth_date(str(data["profile_birth_date"]))
    updated = await complete_onboarding(
        int(user["id"]),
        first_name=str(data.get("profile_first_name") or user.get("first_name") or ""),
        last_name=str(data.get("profile_last_name") or ""),
        gender=str(data.get("profile_gender") or "prefer_not_to_say"),
        birth_date=birth_date,
        age_years=int(data["profile_age_years"]) if data.get("profile_age_years") else None,
        city=str(data.get("profile_city") or ""),
    )
    await state.clear()
    if callback.message:
        await callback.message.answer("Спасибо! Анкета заполнена.")
        updated["current_platform"] = "telegram"
        await send_account_link_suggestion(callback.message, updated)
        await show_main_menu(callback.message, updated)
    await callback.answer()


@router.message(Command("app"))
async def app_command(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    if not has_consultant_binding(user):
        await request_referral_code(message, state)
        return
    await message.answer(
        tr("app.open_text"),
        reply_markup=app_button(user_referral_code(user)),
    )


@router.message(Command("menu"))
async def menu(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    if await continue_onboarding(message, state, user):
        await show_main_menu(message, user)


@router.callback_query(F.data == "menu:main")
async def menu_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    await state.clear()
    if callback.message and await continue_onboarding(callback.message, state, user):
        await show_main_menu(callback.message, user)
    await callback.answer()


@router.message(Command("help"))
async def help_command(message: Message) -> None:
    await message.answer(tr("help.text"), reply_markup=ReplyKeyboardRemove())


@router.message(Command("tests"))
async def tests_command(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    if not await continue_onboarding(message, state, user):
        return
    tests = await list_tests()
    if not tests:
        await message.answer(tr("tests.empty"))
        return
    primary_id = diagnosis_test_id(tests)
    primary = [item for item in tests if int(item["id"]) == int(primary_id or 0)]
    await message.answer("Откройте основной чек-ап организма:", reply_markup=tests_keyboard(primary or tests[:1]))


@router.message(Command("products"))
async def products_command(message: Message) -> None:
    await resolve_user(message)
    products = await list_products()
    if not products:
        await message.answer(tr("products.empty"))
        return

    lines = []
    for item in products[:10]:
        line = f"<b>{html.escape(str(item['title']))}</b>"
        if item.get("short_description"):
            line += f"\n{html.escape(str(item['short_description']))}"
        lines.append(line)
    await message.answer("\n\n".join(lines), parse_mode="HTML")


@router.message(Command("materials"))
async def materials_command(message: Message) -> None:
    user = await resolve_user(message)
    materials = await list_materials(user)
    if not materials:
        await message.answer(tr("materials.empty"))
        return

    await message.answer(tr("materials.choose"), reply_markup=materials_keyboard(materials))


@router.callback_query(F.data == "materials:list")
async def materials_list_callback(callback: CallbackQuery) -> None:
    user = await resolve_telegram_user(callback.from_user)
    materials = await list_materials(user)
    if callback.message:
        if materials:
            await callback.message.answer(tr("materials.choose"), reply_markup=materials_keyboard(materials))
        else:
            await callback.message.answer(tr("materials.empty"))
    await callback.answer()


@router.message(Command("recommendations"))
async def recommendations_command(message: Message) -> None:
    user = await resolve_user(message)
    recommendations = await list_recommendations(user["id"])
    if not recommendations:
        await message.answer(tr("recommendations.empty"))
        return
    text = "\n\n".join(format_recommendation(item) for item in recommendations[:10])
    await message.answer(text, parse_mode="HTML")


@router.message(Command("profile"))
async def profile_command(message: Message) -> None:
    user = await resolve_user(message)
    await message.answer(
        tr(
            "profile.text",
            id=user["id"],
            platform="telegram",
            status=user["status"],
            manager=user.get("manager_id") or tr("profile.no_manager"),
        )
    )


@router.message(Command("manager"))
@router.message(Command("contact_manager"))
async def contact_manager_command(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    if not await continue_onboarding(message, state, user):
        return
    profile = await consultant_profile_for_user(user)
    await message.answer(consultant_contacts_text(profile), parse_mode="HTML")
    await state.set_state(LeadFlow.waiting_message)
    await message.answer("Напишите сообщение консультанту одним сообщением.", reply_markup=ReplyKeyboardRemove())


@router.callback_query(F.data == "lead:contact")
async def contact_manager_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    if callback.message and not await continue_onboarding(callback.message, state, user):
        await callback.answer()
        return
    profile = await consultant_profile_for_user(user)
    await state.set_state(LeadFlow.waiting_message)
    if callback.message:
        await callback.message.answer(consultant_contacts_text(profile), parse_mode="HTML")
        await callback.message.answer("Напишите сообщение консультанту одним сообщением.", reply_markup=ReplyKeyboardRemove())
    await callback.answer()


@router.message(LeadFlow.waiting_message)
async def lead_message(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    text = (message.text or "").strip()
    if not text:
        await message.answer(tr("lead.empty_message"))
        return
    lead_id = await create_lead(user, text)
    client_name = " ".join(
        part for part in [str(user.get("first_name") or "").strip(), str(user.get("last_name") or "").strip()]
        if part
    ) or f"Клиент #{user['id']}"
    await notify_manager_event(
        message.bot,
        user,
        notification_type="consultation_requested",
        event_key=f"consultation_requested:{lead_id}",
        title="Новое обращение",
        message_text=(
            f"Новое обращение #{lead_id}\n\n"
            f"Источник: Telegram\n\n"
            f"Тип: Связь с консультантом\n\n"
            f"Клиент: {client_name}\n\n"
            f"Сообщение:\n{text}\n\n"
            "Ответьте на это сообщение, чтобы отправить ответ клиенту."
        )[:3900],
        lead_id=lead_id,
        source_platform="telegram",
    )
    await state.clear()
    await message.answer("Сообщение отправлено. Консультант свяжется с вами.")


@router.callback_query(F.data.in_({"section:cashback", "section:cooperation"}))
async def profile_section_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    if callback.message and not await continue_onboarding(callback.message, state, user):
        await callback.answer()
        return
    section = (callback.data or "").split(":")[-1]
    if callback.message:
        await send_profile_section(callback.message, user, section)
        if section == "cooperation":
            await callback.message.answer(
                "Чтобы узнать подробности, напишите консультанту.",
                reply_markup=result_actions_keyboard(user_referral_code(user)),
            )
    await callback.answer()


@router.message(Command("privacy"))
async def privacy_command(message: Message) -> None:
    await message.answer(
        "Документы SWPro:\n"
        f"{public_base_url()}/legal.php?type=privacy_policy\n"
        f"{public_base_url()}/legal.php?type=personal_data_consent\n"
        f"{public_base_url()}/legal.php?type=health_data_consent"
    )


@router.message(Command("unsubscribe"))
async def unsubscribe_command(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    await revoke_consents(int(user["id"]), marketing_only=True)
    await state.clear()
    await message.answer("Информационные и рекламные сообщения отключены. Сервисные сообщения чек-апа остаются доступны.")


@router.message(Command("revoke"))
async def revoke_command(message: Message) -> None:
    await message.answer(
        "Отозвать все согласия и прекратить использование SWPro? После этого анкета и чек-ап станут недоступны.",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="Да, отозвать согласия", callback_data="onboarding:revoke_all")],
            [InlineKeyboardButton(text="Отмена", callback_data="menu:main")],
        ]),
    )


@router.callback_query(F.data == "onboarding:revoke_all")
async def revoke_all_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    await revoke_consents(int(user["id"]))
    await state.clear()
    if callback.message:
        await callback.message.answer(
            "Согласия отозваны, сообщения отключены. Для удаления сохранённых данных направьте запрос оператору через контакты в политике."
        )
    await callback.answer()


@router.callback_query(F.data.startswith("test:start:"))
async def test_start_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    if callback.message and not await continue_onboarding(callback.message, state, user):
        await callback.answer()
        return
    test_id = int((callback.data or "").split(":")[-1])
    if callback.message:
        await start_test(callback.message, state, user, test_id)
    await callback.answer()


@router.callback_query(F.data.startswith("test:resume:"))
async def test_resume_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    if callback.message and not await continue_onboarding(callback.message, state, user):
        await callback.answer()
        return
    test_id = int((callback.data or "").split(":")[-1])
    if callback.message:
        await delete_message_silently(callback.message)
        await start_test(callback.message, state, user, test_id, force_resume=True)
    await callback.answer()


@router.callback_query(F.data.startswith("test:result:"))
async def test_result_callback(callback: CallbackQuery) -> None:
    user = await resolve_telegram_user(callback.from_user)
    test_id = int((callback.data or "").split(":")[-1])
    result = await latest_completed_test_result(user["id"], test_id)
    if callback.message and result:
        await send_test_result_message(callback.message, user, result, replace_current=True)
    elif callback.message:
        await callback.message.answer("Результат пока не найден. Можно пройти тест заново.")
    await callback.answer()


@router.callback_query(F.data.startswith("test:restart:"))
async def test_restart_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    if callback.message and not await continue_onboarding(callback.message, state, user):
        await callback.answer()
        return
    test_id = int((callback.data or "").split(":")[-1])
    if callback.message:
        await delete_message_silently(callback.message)
        await start_test(callback.message, state, user, test_id, reset=True)
    await callback.answer()


@router.callback_query(F.data.startswith("material:open:"))
async def material_open_callback(callback: CallbackQuery) -> None:
    user = await resolve_telegram_user(callback.from_user)
    material_id = int((callback.data or "").split(":")[-1])
    material = await get_material(material_id, user)
    if callback.message and material:
        await send_material(callback.message, material)
    elif callback.message:
        await callback.message.answer(tr("materials.empty"))
    await callback.answer()


@router.callback_query(TestFlow.answering, F.data.startswith("test:answer:"))
async def test_answer_callback(callback: CallbackQuery, state: FSMContext) -> None:
    data = await state.get_data()
    answer_id = int((callback.data or "").split(":")[-1])
    question = data["questions"][int(data["index"])]
    if data.get("session_id"):
        await save_session_answer(int(data["session_id"]), int(question["id"]), [answer_id])
    answers = data["answers"]
    answers.append({"question_id": question["id"], "answer_id": answer_id})
    await state.update_data(answers=answers, index=int(data["index"]) + 1)
    if callback.message:
        await send_test_question(callback.message, state, replace_current=True)
    await callback.answer()


@router.callback_query(TestFlow.answering, F.data.startswith("test:multi:"))
async def test_multi_callback(callback: CallbackQuery, state: FSMContext) -> None:
    data = await state.get_data()
    answer_id = int((callback.data or "").split(":")[-1])
    selected = set(map(int, data.get("current_selected", [])))
    if answer_id in selected:
        selected.remove(answer_id)
    else:
        selected.add(answer_id)
    await state.update_data(current_selected=list(selected))
    question = data["questions"][int(data["index"])]
    if callback.message:
        await callback.message.edit_reply_markup(reply_markup=answers_keyboard(question, selected))
    await callback.answer()


@router.callback_query(TestFlow.answering, F.data == "test:done")
async def test_multi_done_callback(callback: CallbackQuery, state: FSMContext) -> None:
    data = await state.get_data()
    selected = list(map(int, data.get("current_selected", [])))
    if not selected:
        await callback.answer("Выберите хотя бы один вариант", show_alert=True)
        return
    question = data["questions"][int(data["index"])]
    if data.get("session_id"):
        await save_session_answer(int(data["session_id"]), int(question["id"]), selected)
    answers = data["answers"]
    for answer_id in selected:
        answers.append({"question_id": question["id"], "answer_id": answer_id})
    await state.update_data(answers=answers, index=int(data["index"]) + 1, current_selected=[])
    if callback.message:
        await send_test_question(callback.message, state, replace_current=True)
    await callback.answer()


@router.message(TestFlow.answering)
async def test_text_answer(message: Message, state: FSMContext) -> None:
    data = await state.get_data()
    question = data["questions"][int(data["index"])]
    if question["question_type"] != "text" and question.get("answers"):
        await message.answer(tr("tests.use_buttons"))
        return
    answers = data["answers"]
    if data.get("session_id"):
        await save_session_answer(
            int(data["session_id"]),
            int(question["id"]),
            text_answer=message.text or "",
        )
    answers.append({"question_id": question["id"], "text_answer": message.text or ""})
    await state.update_data(answers=answers, index=int(data["index"]) + 1)
    await delete_message_by_id_silently(message, data.get("current_question_message_id"))
    await delete_message_silently(message)
    await send_test_question(message, state)


@router.message()
async def fallback(message: Message) -> None:
    user = await resolve_user(message)
    text = (message.text or "").strip()

    if text.isdigit():
        material = await get_material(int(text), user)
        if material:
            await send_material(message, material)
            return

    await message.answer(
        tr("fallback.commands", app_url=mini_app_url(user_referral_code(user))),
        reply_markup=ReplyKeyboardRemove(),
    )
