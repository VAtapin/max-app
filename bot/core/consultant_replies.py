from __future__ import annotations

import json
import os
import random
from urllib.parse import quote

import aiohttp
from bot.core.client_journey import set_client_stage
from bot.db.mysql import cursor


async def consultant_reply_context(
    telegram_chat_id: str,
    replied_message_id: int,
) -> dict | None:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT cn.*,
                   l.source_platform AS lead_source_platform,
                   l.request_type AS lead_request_type,
                   eu.first_name,
                   eu.last_name
            FROM consultant_notifications cn
            INNER JOIN end_users eu ON eu.id = cn.end_user_id
            LEFT JOIN leads l ON l.id = cn.lead_id
            WHERE cn.telegram_chat_id = %s
              AND cn.telegram_message_id = %s
              AND cn.delivery_status = 'sent'
            LIMIT 1
            """,
            (telegram_chat_id, replied_message_id),
        )
        return await cur.fetchone()


async def _ensure_reply_lead(context: dict) -> int:
    lead_id = int(context.get("lead_id") or 0)
    if lead_id:
        return lead_id

    source_platform = str(context.get("source_platform") or "web")
    async with cursor() as cur:
        await cur.execute(
            """
            INSERT INTO leads (
                end_user_id, manager_id, reseller_id, product_id,
                request_type, source_platform, message
            ) VALUES (%s, %s, %s, NULL, 'test_result', %s, %s)
            """,
            (
                context["end_user_id"],
                context.get("manager_id"),
                context.get("reseller_id"),
                source_platform,
                "Консультант начал разбор результатов чек-апа.",
            ),
        )
        lead_id = int(cur.lastrowid)
        await cur.execute(
            """
            UPDATE consultant_notifications
            SET lead_id = %s
            WHERE id = %s AND lead_id IS NULL
            """,
            (lead_id, context["id"]),
        )
    return lead_id


async def telegram_reply_already_saved(
    telegram_chat_id: str,
    telegram_message_id: int,
) -> bool:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT COUNT(*) AS total
            FROM lead_responses
            WHERE telegram_chat_id = %s AND telegram_message_id = %s
            """,
            (telegram_chat_id, telegram_message_id),
        )
        row = await cur.fetchone()
        return bool(row and int(row["total"]) > 0)


async def create_telegram_lead_response(
    context: dict,
    telegram_chat_id: str,
    telegram_message_id: int,
    message_text: str,
    attachment_paths: list[str],
) -> tuple[int, int, bool]:
    lead_id = await _ensure_reply_lead(context)
    attachment_value = (
        json.dumps(attachment_paths, ensure_ascii=False)
        if attachment_paths
        else None
    )
    source_platform = str(
        context.get("lead_source_platform")
        or context.get("source_platform")
        or "web"
    )

    async with cursor() as cur:
        await cur.execute(
            """
            INSERT IGNORE INTO lead_responses (
                lead_id, admin_user_id, response_source,
                telegram_chat_id, telegram_message_id,
                platform, message_text, attachment_path, status
            ) VALUES (%s, NULL, 'telegram', %s, %s, %s, %s, %s, 'pending')
            """,
            (
                lead_id,
                telegram_chat_id,
                telegram_message_id,
                source_platform,
                message_text or None,
                attachment_value,
            ),
        )
        if cur.rowcount == 0:
            await cur.execute(
                """
                SELECT id, lead_id
                FROM lead_responses
                WHERE telegram_chat_id = %s AND telegram_message_id = %s
                LIMIT 1
                """,
                (telegram_chat_id, telegram_message_id),
            )
            existing = await cur.fetchone()
            return int(existing["id"]), int(existing["lead_id"]), False
        return int(cur.lastrowid), lead_id, True


async def telegram_client_chat_id(end_user_id: int) -> str | None:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT platform_user_id
            FROM platform_accounts
            WHERE end_user_id = %s AND platform = 'telegram'
            ORDER BY id DESC
            LIMIT 1
            """,
            (end_user_id,),
        )
        row = await cur.fetchone()
        return str((row or {}).get("platform_user_id") or "").strip() or None


def public_base_url() -> str:
    return os.getenv("SWPRO_PUBLIC_URL", "https://swpro.ru").rstrip("/")


def absolute_public_url(path: str | None) -> str | None:
    value = str(path or "").strip()
    if not value:
        return None
    if value.startswith(("http://", "https://")):
        return value
    return f"{public_base_url()}/{value.lstrip('/')}"


def social_response_text(message_text: str, attachment_paths: list[str]) -> str:
    parts = [message_text.strip() or "Консультант отправил вам вложение."]
    for path in attachment_paths:
        url = absolute_public_url(path)
        if url:
            parts.append(f"Вложение: {url}")
    return "\n\n".join(part for part in parts if part.strip())


async def social_platform_account_id(end_user_id: int, platform: str) -> str | None:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT platform_user_id
            FROM platform_accounts
            WHERE end_user_id = %s AND platform = %s
            ORDER BY id DESC
            LIMIT 1
            """,
            (end_user_id, platform),
        )
        row = await cur.fetchone()
        return str((row or {}).get("platform_user_id") or "").strip() or None


async def social_messaging_integration(platform: str, context: dict) -> dict | None:
    candidates: list[tuple[str, int]] = []
    if context.get("manager_id"):
        candidates.append(("manager", int(context["manager_id"])))
    if context.get("reseller_id"):
        candidates.append(("reseller", int(context["reseller_id"])))

    async with cursor() as cur:
        for owner_type, owner_id in candidates:
            await cur.execute(
                """
                SELECT *
                FROM messaging_integrations
                WHERE platform = %s
                  AND owner_type = %s
                  AND owner_id = %s
                  AND is_active = 1
                  AND access_token IS NOT NULL
                  AND access_token <> ''
                ORDER BY id DESC
                LIMIT 1
                """,
                (platform, owner_type, owner_id),
            )
            integration = await cur.fetchone()
            if integration:
                return integration
    return None


async def vk_messages_allowed(platform_user_id: str) -> tuple[bool, str | None]:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT messages_allowed
            FROM platform_accounts
            WHERE platform = 'VK' AND platform_user_id = %s
            ORDER BY id DESC
            LIMIT 1
            """,
            (platform_user_id,),
        )
        row = await cur.fetchone()
    if row and str(row.get("messages_allowed")) == "0":
        return False, "Клиент запретил сообщения от VK-сообщества"
    return True, None


async def http_form_post(url: str, payload: dict) -> dict:
    timeout = aiohttp.ClientTimeout(total=15)
    async with aiohttp.ClientSession(timeout=timeout) as session:
        async with session.post(url, data=payload) as response:
            try:
                return await response.json(content_type=None)
            except Exception:
                text = await response.text()
                return {"error": text or f"HTTP {response.status}"}


async def http_json_post(url: str, payload: dict) -> dict:
    timeout = aiohttp.ClientTimeout(total=15)
    async with aiohttp.ClientSession(timeout=timeout) as session:
        async with session.post(url, json=payload) as response:
            try:
                return await response.json(content_type=None)
            except Exception:
                text = await response.text()
                return {"error": text or f"HTTP {response.status}"}


async def send_vk_community_message(
    integration: dict,
    platform_user_id: str,
    message_text: str,
) -> tuple[bool, str | None]:
    user_id = "".join(ch for ch in platform_user_id if ch.isdigit())
    if not user_id:
        return False, "VK user_id пустой или неверный"

    allowed, error = await vk_messages_allowed(user_id)
    if not allowed:
        return False, error

    token = str(integration.get("access_token") or "").strip()
    if not token:
        return False, "В VK-подключении не указан ключ доступа"

    response = await http_form_post(
        "https://api.vk.com/method/messages.send",
        {
            "access_token": token,
            "v": os.getenv("VK_API_VERSION", "5.199"),
            "user_id": user_id,
            "random_id": random.randint(1, 2_147_483_647),
            "message": message_text,
        },
    )
    if "response" in response:
        return True, None

    error_data = response.get("error") or response
    if isinstance(error_data, dict):
        return False, str(error_data.get("error_msg") or json.dumps(error_data, ensure_ascii=False))
    return False, str(error_data)


async def send_ok_group_message(
    integration: dict,
    platform_user_id: str,
    message_text: str,
) -> tuple[bool, str | None]:
    token = str(integration.get("access_token") or "").strip()
    if not token:
        return False, "В OK-подключении не указан ключ доступа"

    response = await http_json_post(
        "https://api.ok.ru/graph/me/messages/?access_token=" + quote(token, safe=""),
        {
            "recipient": {"user_id": platform_user_id},
            "message": {"text": message_text},
        },
    )
    success = response.get("success")
    if success is True or (isinstance(success, list) and success and success[0] is True):
        return True, None

    error_data = response.get("error_msg") or response.get("error") or response
    return False, str(error_data)


async def send_social_client_response(
    context: dict,
    platform: str,
    message_text: str,
    attachment_paths: list[str],
) -> tuple[bool, str | None]:
    platform = platform if platform in {"VK", "OK"} else ""
    if not platform:
        return False, "Платформа ответа не поддерживается"

    end_user_id = int(context["end_user_id"])
    platform_user_id = await social_platform_account_id(end_user_id, platform)
    if not platform_user_id:
        return False, f"{platform} клиента не подключён"

    integration = await social_messaging_integration(platform, context)
    if not integration:
        return False, f"Нет активной интеграции сообщества для {platform}"

    outgoing_text = social_response_text(message_text, attachment_paths)
    if platform == "VK":
        return await send_vk_community_message(integration, platform_user_id, outgoing_text)
    return await send_ok_group_message(integration, platform_user_id, outgoing_text)


async def finish_telegram_lead_response(
    response_id: int,
    lead_id: int,
    context: dict,
    ok: bool,
    error: str | None,
    message_text: str,
) -> None:
    end_user_id = int(context["end_user_id"])
    async with cursor() as cur:
        await cur.execute(
            """
            UPDATE lead_responses
            SET status = %s,
                error_message = %s,
                sent_at = IF(%s = 'sent', NOW(), NULL)
            WHERE id = %s
            """,
            ("sent" if ok else "failed", error, "sent" if ok else "failed", response_id),
        )
        if ok:
            await cur.execute(
                """
                UPDATE leads
                SET status = IF(status = 'new', 'contacted', status)
                WHERE id = %s
                """,
                (lead_id,),
            )
            public_url = os.getenv("SWPRO_MINI_APP_URL", "").rstrip("/")
            action_url = f"{public_url}/?page=contact" if public_url else None
            notification_text = message_text.strip() or "Консультант отправил вам файл."
            await cur.execute(
                """
                INSERT INTO user_notifications (
                    end_user_id, notification_type, title, message_text,
                    action_text, action_url
                ) VALUES (%s, 'lead_response', 'Ответ консультанта', %s, %s, %s)
                """,
                (
                    end_user_id,
                    notification_text[:1000],
                    "Открыть ответ",
                    action_url,
                ),
            )

        actor_id = int(context.get("manager_id") or context.get("reseller_id") or 0) or None
        await cur.execute(
            """
            INSERT INTO activity_logs (
                actor_type, actor_id, action, entity_type, entity_id, details
            ) VALUES (%s, %s, 'send_lead_response', 'lead_responses', %s, %s)
            """,
            (
                "manager" if context.get("manager_id") else "reseller",
                actor_id,
                response_id,
                json.dumps(
                    {
                        "lead_id": lead_id,
                        "source": "telegram_reply",
                        "status": "sent" if ok else "failed",
                    },
                    ensure_ascii=False,
                ),
            ),
        )

    if ok:
        await set_client_stage(
            end_user_id,
            "in_progress",
            "consultant" if context.get("manager_id") else "leader",
            int(context.get("manager_id") or context.get("reseller_id") or 0) or None,
            "Консультант ответил на обращение через Telegram",
        )
