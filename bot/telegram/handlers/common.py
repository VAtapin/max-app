from __future__ import annotations

import html
import os

from aiogram import F, Router
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import CallbackQuery, Message, ReplyKeyboardRemove, User

from bot.core.i18n import tr
from bot.core.leads import create_lead
from bot.core.materials import get_material, list_materials
from bot.core.messages import MEDICAL_DISCLAIMER
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
from bot.core.users import StaffAccountError, get_or_create_user
from bot.telegram.keyboards.menu import (
    app_button,
    answers_keyboard,
    completed_test_keyboard,
    main_menu_keyboard,
    materials_keyboard,
    mini_app_url,
    result_actions_keyboard,
    resume_test_keyboard,
    tests_keyboard,
)

router = Router()


class TestFlow(StatesGroup):
    answering = State()


class LeadFlow(StatesGroup):
    waiting_message = State()


async def resolve_user(message: Message, referral_code: str | None = None) -> dict:
    return await resolve_telegram_user(message.from_user, referral_code)


async def resolve_telegram_user(tg_user: User | None, referral_code: str | None = None) -> dict:
    if tg_user is None:
        raise RuntimeError("Telegram user is missing")

    return await get_or_create_user(
        platform="telegram",
        platform_user_id=str(tg_user.id),
        username=tg_user.username,
        first_name=tg_user.first_name,
        last_name=tg_user.last_name,
        referral_code=referral_code,
    )


def referral_from_start(text: str | None) -> str | None:
    parts = (text or "").split(maxsplit=1)
    return parts[1].strip() if len(parts) > 1 else None


def user_referral_code(user: dict) -> str | None:
    return user.get("referral_code_used")


def diagnosis_test_id(tests: list[dict]) -> int | None:
    for item in tests:
        title = str(item.get("title") or "").lower()
        if "диагност" in title:
            return int(item["id"])
    return int(tests[0]["id"]) if tests else None


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
    base_url = os.getenv("SWPRO_PUBLIC_BASE_URL", "https://swpro.ru").rstrip("/")
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
    text = (
        f"<b>{html.escape(str(result['title']))}</b>"
        f"{score_line}\n\n"
        f"{html.escape(str(result['summary']))}\n\n"
        f"{html.escape(MEDICAL_DISCLAIMER)}"
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

    materials = await list_materials(user)
    if materials:
        await message.answer(
            "Материалы по результату:",
            reply_markup=materials_keyboard(materials[:5]),
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
        await state.clear()
        await send_test_result_message(message, data.get("user", {}), result, replace_current=replace_current)
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
    test = await get_test(test_id)
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
async def start(message: Message) -> None:
    try:
        user = await resolve_user(message, referral_from_start(message.text))
    except StaffAccountError:
        await message.answer(tr("staff.client_registration_blocked"), reply_markup=ReplyKeyboardRemove())
        return

    first_name = message.from_user.first_name if message.from_user else ""
    tests = await list_tests()
    await message.answer(
        tr("welcome.short", name=first_name, disclaimer=MEDICAL_DISCLAIMER),
        reply_markup=ReplyKeyboardRemove(),
    )
    await message.answer(
        "Выберите, с чего начнем:",
        reply_markup=main_menu_keyboard(user_referral_code(user), diagnosis_test_id(tests)),
    )


@router.message(Command("app"))
async def app_command(message: Message) -> None:
    user = await resolve_user(message)
    await message.answer(
        tr("app.open_text"),
        reply_markup=app_button(user_referral_code(user)),
    )


@router.message(Command("menu"))
async def menu(message: Message) -> None:
    user = await resolve_user(message)
    tests = await list_tests()
    await message.answer(
        "Выберите нужный раздел:",
        reply_markup=main_menu_keyboard(user_referral_code(user), diagnosis_test_id(tests)),
    )


@router.message(Command("help"))
async def help_command(message: Message) -> None:
    await message.answer(tr("help.text", disclaimer=MEDICAL_DISCLAIMER), reply_markup=ReplyKeyboardRemove())


@router.message(Command("tests"))
async def tests_command(message: Message) -> None:
    await resolve_user(message)
    tests = await list_tests()
    if not tests:
        await message.answer(tr("tests.empty"))
        return
    await message.answer(tr("tests.choose"), reply_markup=tests_keyboard(tests))


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
    await message.answer("\n\n".join(lines) + "\n\n" + html.escape(MEDICAL_DISCLAIMER), parse_mode="HTML")


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
    await message.answer(text + "\n\n" + html.escape(MEDICAL_DISCLAIMER), parse_mode="HTML")


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
    await resolve_user(message)
    await state.set_state(LeadFlow.waiting_message)
    await message.answer(tr("lead.ask_message"), reply_markup=ReplyKeyboardRemove())


@router.callback_query(F.data == "lead:contact")
async def contact_manager_callback(callback: CallbackQuery, state: FSMContext) -> None:
    await resolve_telegram_user(callback.from_user)
    await state.set_state(LeadFlow.waiting_message)
    if callback.message:
        await callback.message.answer(tr("lead.ask_message"), reply_markup=ReplyKeyboardRemove())
    await callback.answer()


@router.message(LeadFlow.waiting_message)
async def lead_message(message: Message, state: FSMContext) -> None:
    user = await resolve_user(message)
    text = (message.text or "").strip()
    if not text:
        await message.answer(tr("lead.empty_message"))
        return
    lead_id = await create_lead(user, text)
    await state.clear()
    await message.answer(tr("lead.created_manager", id=lead_id))


@router.callback_query(F.data.startswith("test:start:"))
async def test_start_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
    test_id = int((callback.data or "").split(":")[-1])
    if callback.message:
        await start_test(callback.message, state, user, test_id)
    await callback.answer()


@router.callback_query(F.data.startswith("test:resume:"))
async def test_resume_callback(callback: CallbackQuery, state: FSMContext) -> None:
    user = await resolve_telegram_user(callback.from_user)
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
