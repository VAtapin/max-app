from aiogram import Router
from aiogram.filters import Command
from aiogram.types import CallbackQuery, Message, ReplyKeyboardRemove, User

from bot.core.i18n import tr
from bot.core.leads import create_lead
from bot.core.messages import MEDICAL_DISCLAIMER, welcome_text
from bot.core.products import list_categories, list_products
from bot.core.recommendations import list_recommendations
from bot.core.tests import list_tests
from bot.core.users import StaffAccountError, get_or_create_user
from bot.telegram.keyboards.menu import mini_app_keyboard

router = Router()


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


@router.message(Command("start"))
async def start(message: Message) -> None:
    parts = (message.text or "").split(maxsplit=1)
    referral_code = parts[1].strip() if len(parts) > 1 else None
    try:
        await resolve_user(message, referral_code)
    except StaffAccountError:
        await message.answer(tr("staff.client_registration_blocked"))
        return
    first_name = message.from_user.first_name if message.from_user else None
    await message.answer(
        welcome_text(first_name),
        reply_markup=ReplyKeyboardRemove(),
    )
    await message.answer(tr("start.open_app"), reply_markup=mini_app_keyboard())


@router.message(Command("menu"))
async def menu(message: Message) -> None:
    await resolve_user(message)
    await message.answer(tr("menu.title"), reply_markup=ReplyKeyboardRemove())
    await message.answer(tr("menu.choose"), reply_markup=mini_app_keyboard())


@router.message(Command("help"))
async def help_command(message: Message) -> None:
    await message.answer(tr("help.text", disclaimer=MEDICAL_DISCLAIMER), reply_markup=mini_app_keyboard())


@router.message(Command("tests"))
async def tests_command(message: Message) -> None:
    tests = await list_tests()
    if not tests:
        await message.answer(tr("tests.empty"))
        return
    items = "\n".join(f"{item['id']}. {item['title']}" for item in tests)
    await message.answer(tr("tests.available", items=items))


@router.message(Command("products"))
async def products_command(message: Message) -> None:
    categories = await list_categories()
    products = await list_products()
    lines = [tr("products.categories")]
    lines.extend(f"- {item['title']}" for item in categories)
    lines.append("\n" + tr("products.title"))
    lines.extend(f"- {item['title']}: {item.get('short_description') or ''}" for item in products[:10])
    await message.answer("\n".join(lines) + "\n\n" + MEDICAL_DISCLAIMER)


@router.message(Command("profile"))
async def profile_command(message: Message) -> None:
    user = await resolve_user(message)
    await message.answer(tr("profile.text", id=user["id"], platform="telegram", status=user["status"]))


@router.message(Command("contact_manager"))
async def contact_manager_command(message: Message) -> None:
    user = await resolve_user(message)
    lead_id = await create_lead(user, tr("lead.contact_request"))
    await message.answer(tr("lead.created_manager", id=lead_id))


@router.message()
async def menu_text(message: Message) -> None:
    text = (message.text or "").strip()
    user = await resolve_user(message)

    if text == tr("menu.tests"):
        await tests_command(message)
    elif text == tr("menu.recommendations"):
        recommendations = await list_recommendations(user["id"])
        if not recommendations:
            await message.answer(tr("recommendations.empty"))
            return
        lines = [f"- {item.get('product_title') or tr('recommendations.default')}" for item in recommendations[:10]]
        await message.answer(tr("recommendations.title", items="\n".join(lines), disclaimer=MEDICAL_DISCLAIMER))
    elif text == tr("menu.products"):
        await products_command(message)
    elif text == tr("menu.contact_manager"):
        await contact_manager_command(message)
    elif text == tr("menu.profile"):
        await profile_command(message)
    elif text == tr("menu.help"):
        await help_command(message)
    else:
        await message.answer(tr("fallback.open_app"), reply_markup=mini_app_keyboard())


@router.callback_query()
async def callback_menu(callback: CallbackQuery) -> None:
    if not callback.message:
        await callback.answer()
        return

    data = callback.data or ""
    if data == "products":
        products = await list_products()
        text = "\n".join(f"- {item['title']}: {item.get('short_description') or ''}" for item in products[:10])
        await callback.message.answer((text or tr("products.empty")) + "\n\n" + MEDICAL_DISCLAIMER, reply_markup=mini_app_keyboard())
    elif data == "recommendations":
        user = await resolve_telegram_user(callback.from_user)
        recommendations = await list_recommendations(user["id"])
        if recommendations:
            lines = [f"- {item.get('product_title') or tr('recommendations.default')}" for item in recommendations[:10]]
            await callback.message.answer(tr("recommendations.title", items="\n".join(lines), disclaimer=MEDICAL_DISCLAIMER), reply_markup=mini_app_keyboard())
        else:
            await callback.message.answer(tr("recommendations.empty_open_app"), reply_markup=mini_app_keyboard())
    elif data == "contact_manager":
        user = await resolve_telegram_user(callback.from_user)
        lead_id = await create_lead(user, tr("lead.contact_request"))
        await callback.message.answer(tr("lead.created_manager", id=lead_id), reply_markup=mini_app_keyboard())

    await callback.answer()
