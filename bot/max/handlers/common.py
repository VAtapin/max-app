from bot.core.i18n import tr
from bot.core.leads import create_lead
from bot.core.messages import welcome_text
from bot.core.products import list_products
from bot.core.recommendations import list_recommendations
from bot.core.tests import list_tests
from bot.core.users import get_or_create_user
from bot.max.adapter import MaxBotAdapter


async def handle_update(adapter: MaxBotAdapter, update: dict) -> None:
    message = update.get("message", {})
    sender = message.get("sender", {})
    text = (message.get("text") or "").strip()
    chat_id = str(message.get("chat_id") or sender.get("user_id"))
    platform_user_id = str(sender.get("user_id"))
    referral_code = None

    if text.startswith("/start"):
        parts = text.split(maxsplit=1)
        referral_code = parts[1] if len(parts) > 1 else None

    user = await get_or_create_user(
        platform="max",
        platform_user_id=platform_user_id,
        username=sender.get("username"),
        first_name=sender.get("first_name"),
        last_name=sender.get("last_name"),
        referral_code=referral_code,
    )

    if text.startswith("/start"):
        await adapter.send_message(chat_id, welcome_text(sender.get("first_name")))
    elif text in {"/tests", tr("menu.tests")}:
        tests = await list_tests()
        await adapter.send_message(chat_id, "\n".join(f"{item['id']}. {item['title']}" for item in tests) or tr("tests.empty"))
    elif text in {"/products", tr("menu.products")}:
        products = await list_products()
        await adapter.send_message(chat_id, "\n".join(f"- {item['title']}" for item in products[:10]) or tr("products.empty"))
    elif text in {"/recommendations", tr("menu.recommendations")}:
        recommendations = await list_recommendations(user["id"])
        await adapter.send_message(chat_id, "\n".join(f"- {item.get('product_title')}" for item in recommendations[:10]) or tr("recommendations.empty"))
    elif text in {"/contact_manager", tr("menu.contact_manager")}:
        lead_id = await create_lead(user, tr("lead.contact_request"))
        await adapter.send_message(chat_id, tr("lead.created", id=lead_id))
    else:
        await adapter.send_message(chat_id, tr("max.menu"))
