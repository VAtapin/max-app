from aiogram import Router
from aiogram.filters import Command
from aiogram.types import CallbackQuery, Message, ReplyKeyboardRemove, User

from bot.core.leads import create_lead
from bot.core.messages import MEDICAL_DISCLAIMER, welcome_text
from bot.core.products import list_categories, list_products
from bot.core.recommendations import list_recommendations
from bot.core.tests import list_tests
from bot.core.users import get_or_create_user
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
    await resolve_user(message, referral_code)
    await message.answer(
        welcome_text(message.from_user.first_name),
        reply_markup=ReplyKeyboardRemove(),
    )
    await message.answer("Откройте SWPro Mini App или используйте быстрые действия ниже.", reply_markup=mini_app_keyboard())


@router.message(Command("menu"))
async def menu(message: Message) -> None:
    await resolve_user(message)
    await message.answer("Главное меню", reply_markup=ReplyKeyboardRemove())
    await message.answer("Выберите действие.", reply_markup=mini_app_keyboard())


@router.message(Command("help"))
async def help_command(message: Message) -> None:
    await message.answer("Откройте SWPro Mini App или используйте команды /products, /tests, /contact_manager.\n\n" + MEDICAL_DISCLAIMER, reply_markup=mini_app_keyboard())


@router.message(Command("tests"))
async def tests_command(message: Message) -> None:
    tests = await list_tests()
    if not tests:
        await message.answer("Активных тестов пока нет.")
        return
    text = "\n".join(f"{item['id']}. {item['title']}" for item in tests)
    await message.answer("Доступные тесты:\n" + text)


@router.message(Command("products"))
async def products_command(message: Message) -> None:
    categories = await list_categories()
    products = await list_products()
    lines = ["Категории:"]
    lines.extend(f"- {item['title']}" for item in categories)
    lines.append("\nПродукты:")
    lines.extend(f"- {item['title']}: {item.get('short_description') or ''}" for item in products[:10])
    await message.answer("\n".join(lines) + "\n\n" + MEDICAL_DISCLAIMER)


@router.message(Command("profile"))
async def profile_command(message: Message) -> None:
    user = await resolve_user(message)
    await message.answer(
        f"Ваш профиль\nID: {user['id']}\nПлатформа: telegram\nСтатус: {user['status']}"
    )


@router.message(Command("contact_manager"))
async def contact_manager_command(message: Message) -> None:
    user = await resolve_user(message)
    lead_id = await create_lead(user, "Пользователь запросил связь с менеджером.")
    await message.answer(f"Заявка #{lead_id} создана. Менеджер свяжется с вами.")


@router.message()
async def menu_text(message: Message) -> None:
    text = (message.text or "").strip()
    user = await resolve_user(message)

    if text == "Пройти тест":
        await tests_command(message)
    elif text == "Мои рекомендации":
        recommendations = await list_recommendations(user["id"])
        if not recommendations:
            await message.answer("Рекомендаций пока нет. Сначала пройдите тест.")
            return
        lines = [f"- {item.get('product_title') or 'Рекомендация'}" for item in recommendations[:10]]
        await message.answer("Ваши рекомендации:\n" + "\n".join(lines) + "\n\n" + MEDICAL_DISCLAIMER)
    elif text == "Каталог продуктов":
        await products_command(message)
    elif text == "Связаться с менеджером":
        await contact_manager_command(message)
    elif text == "Мой профиль":
        await profile_command(message)
    elif text == "Помощь":
        await help_command(message)
    else:
        await message.answer("Откройте SWPro Mini App или выберите действие ниже.", reply_markup=mini_app_keyboard())


@router.callback_query()
async def callback_menu(callback: CallbackQuery) -> None:
    if not callback.message:
        await callback.answer()
        return

    data = callback.data or ""
    if data == "products":
        products = await list_products()
        text = "\n".join(f"- {item['title']}: {item.get('short_description') or ''}" for item in products[:10])
        await callback.message.answer((text or "Продуктов пока нет.") + "\n\n" + MEDICAL_DISCLAIMER, reply_markup=mini_app_keyboard())
    elif data == "recommendations":
        user = await resolve_telegram_user(callback.from_user)
        recommendations = await list_recommendations(user["id"])
        if recommendations:
            lines = [f"- {item.get('product_title') or 'Рекомендация'}" for item in recommendations[:10]]
            await callback.message.answer("Ваши рекомендации:\n" + "\n".join(lines) + "\n\n" + MEDICAL_DISCLAIMER, reply_markup=mini_app_keyboard())
        else:
            await callback.message.answer("Рекомендаций пока нет. Откройте SWPro и пройдите тест.", reply_markup=mini_app_keyboard())
    elif data == "contact_manager":
        user = await resolve_telegram_user(callback.from_user)
        lead_id = await create_lead(user, "Пользователь запросил связь с менеджером.")
        await callback.message.answer(f"Заявка #{lead_id} создана. Менеджер свяжется с вами.", reply_markup=mini_app_keyboard())

    await callback.answer()
