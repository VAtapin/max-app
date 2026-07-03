from __future__ import annotations

from datetime import date, datetime
from zoneinfo import available_timezones

from bot.db.mysql import cursor

REQUIRED_CONSENTS = ("personal_data_consent", "health_data_consent", "user_agreement")
GENDERS = {"female", "male", "prefer_not_to_say"}


async def active_legal_documents() -> dict[str, dict]:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT ld.*
            FROM legal_documents ld
            INNER JOIN (
                SELECT document_type, MAX(id) AS max_id
                FROM legal_documents
                WHERE is_active = 1
                GROUP BY document_type
            ) latest ON latest.max_id = ld.id
            """
        )
        return {row["document_type"]: row for row in await cur.fetchall()}


async def latest_consents(end_user_id: int) -> dict[str, dict]:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT uc.*
            FROM user_consents uc
            INNER JOIN (
                SELECT document_type, MAX(id) AS max_id
                FROM user_consents
                WHERE end_user_id = %s
                GROUP BY document_type
            ) latest ON latest.max_id = uc.id
            """,
            (end_user_id,),
        )
        return {row["document_type"]: row for row in await cur.fetchall()}


async def onboarding_status(user: dict) -> dict:
    documents = await active_legal_documents()
    consents = await latest_consents(int(user["id"]))
    missing = []
    for consent_type in REQUIRED_CONSENTS:
        document = documents.get(consent_type)
        consent = consents.get(consent_type)
        if (
            not document
            or not consent
            or consent.get("revoked_at")
            or str(consent["document_version"]) != str(document["version"])
        ):
            missing.append(consent_type)

    profile_complete = bool(
        str(user.get("first_name") or "").strip()
        and str(user.get("last_name") or "").strip()
        and user.get("gender")
        and (user.get("birth_date") or user.get("age_years"))
        and str(user.get("city") or "").strip()
    )
    marketing = consents.get("marketing_consent")
    marketing_document = documents.get("marketing_consent")
    marketing_granted = bool(
        marketing
        and marketing_document
        and not marketing.get("revoked_at")
        and str(marketing["document_version"]) == str(marketing_document["version"])
    )

    return {
        "complete": not missing and profile_complete and bool(user.get("onboarding_completed_at")),
        "missing_consents": missing,
        "profile_complete": profile_complete,
        "marketing_consent": marketing_granted,
        "documents": documents,
    }


async def grant_consent(end_user_id: int, consent_type: str, platform: str) -> None:
    if consent_type not in (*REQUIRED_CONSENTS, "marketing_consent"):
        raise ValueError("Unknown consent type")

    documents = await active_legal_documents()
    document = documents.get(consent_type)
    if not document:
        raise RuntimeError("Active legal document is missing")

    consents = await latest_consents(end_user_id)
    existing = consents.get(consent_type)
    if (
        existing
        and not existing.get("revoked_at")
        and str(existing["document_version"]) == str(document["version"])
    ):
        return

    async with cursor() as cur:
        await cur.execute(
            """
            INSERT INTO user_consents (
                end_user_id, document_type, document_version, platform, metadata_json
            ) VALUES (%s, %s, %s, %s, JSON_OBJECT('source', 'telegram_bot'))
            """,
            (end_user_id, consent_type, document["version"], platform),
        )
        if consent_type == "marketing_consent":
            await cur.execute(
                "UPDATE end_users SET notifications_enabled = 1 WHERE id = %s",
                (end_user_id,),
            )


async def revoke_consents(end_user_id: int, marketing_only: bool = False) -> None:
    consent_types = ("marketing_consent",) if marketing_only else (
        "personal_data_consent",
        "health_data_consent",
        "marketing_consent",
        "user_agreement",
    )
    placeholders = ",".join(["%s"] * len(consent_types))
    async with cursor() as cur:
        await cur.execute(
            f"""
            UPDATE user_consents
            SET revoked_at = NOW()
            WHERE end_user_id = %s
              AND document_type IN ({placeholders})
              AND revoked_at IS NULL
            """,
            (end_user_id, *consent_types),
        )
        if marketing_only:
            return
        else:
            await cur.execute(
                """
                UPDATE end_users
                SET notifications_enabled = 0,
                    status = 'unsubscribed'
                WHERE id = %s
                """,
                (end_user_id,),
            )
    if not marketing_only:
        await set_client_stage(end_user_id, "unsubscribed", "client")


async def set_client_stage(
    end_user_id: int,
    new_stage: str,
    source: str = "system",
    actor_id: int | None = None,
    note: str | None = None,
) -> None:
    async with cursor() as cur:
        await cur.execute(
            "SELECT client_stage FROM end_users WHERE id = %s LIMIT 1",
            (end_user_id,),
        )
        row = await cur.fetchone()
        if not row or row["client_stage"] == new_stage:
            return
        previous = row["client_stage"]
        automatic_order = {
            "new": 0,
            "profile_completed": 10,
            "test_started": 20,
            "test_completed": 30,
            "consultation_requested": 40,
        }
        if (
            source == "system"
            and previous in automatic_order
            and new_stage in automatic_order
            and automatic_order[new_stage] < automatic_order[previous]
        ):
            return
        await cur.execute(
            """
            UPDATE end_users
            SET client_stage = %s, stage_updated_at = NOW()
            WHERE id = %s
            """,
            (new_stage, end_user_id),
        )
        await cur.execute(
            """
            INSERT INTO client_stage_history (
                end_user_id, previous_stage, new_stage, source, actor_id, note
            ) VALUES (%s, %s, %s, %s, %s, %s)
            """,
            (end_user_id, previous, new_stage, source, actor_id, note),
        )


async def complete_onboarding(
    end_user_id: int,
    *,
    first_name: str,
    last_name: str,
    gender: str,
    birth_date: date | None,
    age_years: int | None,
    city: str,
    timezone: str = "Europe/Moscow",
) -> dict:
    if gender not in GENDERS:
        raise ValueError("Invalid gender")
    if not first_name.strip() or not last_name.strip() or not city.strip():
        raise ValueError("First name, last name and city are required")
    if age_years is not None and not 14 <= age_years <= 100:
        raise ValueError("Invalid age")
    if birth_date is None and age_years is None:
        raise ValueError("Birth date or age is required")
    if timezone not in available_timezones():
        timezone = "Europe/Moscow"

    async with cursor() as cur:
        await cur.execute(
            """
            UPDATE end_users
            SET first_name = %s,
                last_name = %s,
                gender = %s,
                birth_date = %s,
                age_years = %s,
                city = %s,
                timezone = %s,
                onboarding_completed_at = NOW(),
                notifications_enabled = 1,
                status = 'active',
                last_activity_at = NOW()
            WHERE id = %s
            """,
            (
                first_name.strip(),
                last_name.strip(),
                gender,
                birth_date,
                age_years,
                city.strip(),
                timezone,
                end_user_id,
            ),
        )
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (end_user_id,))
        updated = await cur.fetchone()
    await set_client_stage(end_user_id, "profile_completed", "client")
    return updated


async def consultant_profile_for_user(user: dict) -> dict | None:
    owner_type = None
    owner_id = None
    if user.get("manager_id"):
        owner_type, owner_id = "manager", int(user["manager_id"])
    elif user.get("reseller_id"):
        owner_type, owner_id = "reseller", int(user["reseller_id"])
    if not owner_type:
        return None

    async with cursor() as cur:
        await cur.execute(
            """
            SELECT cp.*
            FROM consultant_profiles cp
            WHERE cp.owner_type = %s AND cp.owner_id = %s
            LIMIT 1
            """,
            (owner_type, owner_id),
        )
        return await cur.fetchone()


async def manager_telegram_id(user: dict) -> str | None:
    recipient = await consultant_telegram_recipient(user)
    return str((recipient or {}).get("telegram_id") or "").strip() or None


async def consultant_telegram_recipient(user: dict) -> dict | None:
    manager_id = int(user.get("manager_id") or 0)
    reseller_id = int(user.get("reseller_id") or 0)
    async with cursor() as cur:
        if manager_id:
            await cur.execute(
                """
                SELECT COALESCE(
                    NULLIF(m.telegram_id, ''),
                    (
                        SELECT NULLIF(au.telegram_id, '')
                        FROM admin_users au
                        WHERE au.manager_id = m.id
                          AND au.role = 'manager'
                          AND au.is_active = 1
                        ORDER BY au.id
                        LIMIT 1
                    )
                ) AS telegram_id
                FROM managers m
                WHERE m.id = %s AND m.is_active = 1
                LIMIT 1
                """,
                (manager_id,),
            )
            row = await cur.fetchone()
            return {
                "manager_id": manager_id,
                "reseller_id": None,
                "telegram_id": str((row or {}).get("telegram_id") or "").strip(),
            }

        if not reseller_id:
            return None
        await cur.execute(
            """
            SELECT telegram_id
            FROM admin_users
            WHERE reseller_id = %s
              AND role = 'reseller'
              AND is_active = 1
              AND telegram_id IS NOT NULL
              AND telegram_id <> ''
            ORDER BY id
            LIMIT 1
            """,
            (reseller_id,),
        )
        row = await cur.fetchone()
        return {
            "manager_id": None,
            "reseller_id": reseller_id,
            "telegram_id": str((row or {}).get("telegram_id") or "").strip(),
        }


async def create_consultant_notification(
    user: dict,
    notification_type: str,
    event_key: str,
    title: str,
    message: str,
    *,
    lead_id: int | None = None,
    source_platform: str | None = None,
) -> dict | None:
    recipient = await consultant_telegram_recipient(user)
    if not recipient:
        return None

    async with cursor() as cur:
        await cur.execute(
            """
            INSERT IGNORE INTO consultant_notifications (
                manager_id, reseller_id, end_user_id, lead_id, notification_type,
                source_platform, event_key, title, message_text
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """,
            (
                recipient["manager_id"],
                recipient["reseller_id"],
                user["id"],
                lead_id,
                notification_type,
                source_platform,
                event_key,
                title,
                message,
            ),
        )
        if cur.rowcount == 0:
            return None
        return recipient | {"notification_id": int(cur.lastrowid)}


async def mark_consultant_notification_delivery(
    notification_id: int,
    ok: bool,
    error: str | None = None,
    *,
    telegram_chat_id: str | None = None,
    telegram_message_id: int | None = None,
) -> None:
    async with cursor() as cur:
        await cur.execute(
            """
            UPDATE consultant_notifications
            SET delivery_status = %s,
                delivery_error = %s,
                telegram_chat_id = %s,
                telegram_message_id = %s
            WHERE id = %s
            """,
            (
                "sent" if ok else "failed",
                error,
                telegram_chat_id,
                telegram_message_id,
                notification_id,
            ),
        )


def parse_age_or_birth_date(value: str) -> tuple[date | None, int | None]:
    text = value.strip()
    if text.isdigit():
        age = int(text)
        if 14 <= age <= 100:
            return None, age
        raise ValueError("Invalid age")

    for fmt in ("%d.%m.%Y", "%Y-%m-%d"):
        try:
            parsed = datetime.strptime(text, fmt).date()
            today = date.today()
            age = today.year - parsed.year - ((today.month, today.day) < (parsed.month, parsed.day))
            if 14 <= age <= 100:
                return parsed, None
        except ValueError:
            continue
    raise ValueError("Invalid date")
