from __future__ import annotations

import json
import os

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
