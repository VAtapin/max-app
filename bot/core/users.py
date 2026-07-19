from __future__ import annotations

from contextlib import asynccontextmanager
from datetime import date, datetime
import hashlib
import hmac
import os
import time
from urllib.parse import quote, urlencode

import aiomysql

from bot.core.environment import required_env
from bot.core.referrals import (
    increment_registration,
    normalize_referral_code,
    resolve_referral,
)
from bot.db.mysql import cursor, init_pool


class StaffAccountError(RuntimeError):
    pass


@asynccontextmanager
async def transaction_cursor():
    pool = await init_pool()
    async with pool.acquire() as conn:
        await conn.begin()
        try:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                yield cur
            await conn.commit()
        except Exception:
            await conn.rollback()
            raise


def account_link_secret() -> str:
    bot_token = os.getenv("TELEGRAM_BOT_TOKEN", "")
    db_password = required_env("DB_PASSWORD")
    return hashlib.sha256(f"{bot_token}|{db_password}|swpro-account-link".encode()).hexdigest()


def create_account_link_token(end_user_id: int, ttl_seconds: int = 900) -> str:
    expires_at = int(time.time()) + ttl_seconds
    payload = f"{end_user_id}|{expires_at}"
    signature = hmac.new(account_link_secret().encode(), payload.encode(), hashlib.sha256).hexdigest()[:20]
    return f"l_{end_user_id}_{expires_at}_{signature}"


def parse_account_link_token(token: str | None) -> int | None:
    if not token:
        return None
    token = token.removeprefix("link_")
    parts = token.split("_")
    if len(parts) != 4 or parts[0] != "l":
        return None
    _, end_user_id, expires_at, signature = parts
    try:
        end_user_id_int = int(end_user_id)
        expires_at_int = int(expires_at)
    except ValueError:
        return None
    if end_user_id_int <= 0 or expires_at_int < int(time.time()):
        return None
    payload = f"{end_user_id_int}|{expires_at_int}"
    expected = hmac.new(account_link_secret().encode(), payload.encode(), hashlib.sha256).hexdigest()[:20]
    if not hmac.compare_digest(expected, signature):
        return None
    return end_user_id_int


def append_query_param(url: str, name: str, value: str) -> str:
    separator = "&" if "?" in url else "?"
    return f"{url}{separator}{urlencode({name: value})}"


def account_link_urls(token: str) -> dict[str, str]:
    mini_app_url = os.getenv("SWPRO_MINI_APP_URL", "https://swpro.ru/vk-mini-app/").strip()
    telegram_bot = os.getenv("TELEGRAM_BOT_USERNAME", "SWProAssistant_bot").strip().lstrip("@")
    vk_app_id = os.getenv("VK_APP_ID", "").strip()
    return {
        "mini_app": append_query_param(mini_app_url, "link_token", token) if mini_app_url else "",
        "telegram": f"https://t.me/{quote(telegram_bot)}?start={quote('link_' + token)}" if telegram_bot else "",
        "vk": f"https://vk.com/app{quote(vk_app_id)}#link_token={quote(token)}" if vk_app_id else "",
    }


def account_link_payload(user: dict, ttl_seconds: int = 900) -> dict:
    token = create_account_link_token(int(user["id"]), ttl_seconds)
    return {
        "token": token,
        "expires_in": ttl_seconds,
        "links": account_link_urls(token),
    }


def platform_label(platform: str | None) -> str:
    return {
        "telegram": "Telegram",
        "VK": "VK",
        "OK": "OK",
        "MAX": "MAX",
        "web": "Web",
    }.get(str(platform or ""), str(platform or ""))


def profile_age(user: dict) -> int | None:
    birth_date = user.get("birth_date")
    if birth_date:
        try:
            if isinstance(birth_date, date):
                parsed = birth_date
            else:
                parsed = datetime.fromisoformat(str(birth_date)).date()
            today = date.today()
            return today.year - parsed.year - ((today.month, today.day) < (parsed.month, parsed.day))
        except ValueError:
            return None
    age_years = int(user.get("age_years") or 0)
    return age_years if age_years > 0 else None


async def link_existing_user_to_target(target_user_id: int, source_user_id: int) -> dict | None:
    if target_user_id <= 0 or source_user_id <= 0 or target_user_id == source_user_id:
        return None

    async with transaction_cursor() as cur:
        await cur.execute(
            "SELECT * FROM end_users WHERE id = %s AND merged_into_user_id IS NULL FOR UPDATE",
            (target_user_id,),
        )
        target = await cur.fetchone()
        await cur.execute(
            "SELECT * FROM end_users WHERE id = %s AND merged_into_user_id IS NULL FOR UPDATE",
            (source_user_id,),
        )
        source = await cur.fetchone()
        if not target or not source:
            return None

        await cur.execute(
            """
            DELETE source_log
            FROM automation_logs source_log
            INNER JOIN automation_logs target_log
              ON target_log.end_user_id = %s
             AND target_log.automation_type = source_log.automation_type
             AND target_log.context_key = source_log.context_key
             AND target_log.platform = source_log.platform
            WHERE source_log.end_user_id = %s
            """,
            (target_user_id, source_user_id),
        )

        for table, column in {
            "platform_accounts": "end_user_id",
            "leads": "end_user_id",
            "user_test_sessions": "end_user_id",
            "recommendations": "end_user_id",
            "broadcast_logs": "end_user_id",
            "user_consents": "end_user_id",
            "client_stage_history": "end_user_id",
            "user_notifications": "end_user_id",
            "automation_logs": "end_user_id",
            "consultant_notifications": "end_user_id",
        }.items():
            await cur.execute(
                f"UPDATE {table} SET {column} = %s WHERE {column} = %s",
                (target_user_id, source_user_id),
            )

        assignments: list[str] = []
        params: list[object] = []
        for field in (
            "username",
            "first_name",
            "last_name",
            "gender",
            "birth_date",
            "age_years",
            "city",
            "phone",
            "email",
            "referral_code_used",
            "onboarding_completed_at",
        ):
            if not str(target.get(field) or "").strip() and str(source.get(field) or "").strip():
                assignments.append(f"{field} = %s")
                params.append(source[field])

        if (
            not target.get("reseller_id")
            and not target.get("manager_id")
            and (source.get("reseller_id") or source.get("manager_id"))
        ):
            assignments.extend(["reseller_id = %s", "manager_id = %s"])
            params.extend([source.get("reseller_id"), source.get("manager_id")])

        if assignments:
            await cur.execute(
                f"UPDATE end_users SET {', '.join(assignments)} WHERE id = %s",
                (*params, target_user_id),
            )

        await cur.execute(
            "UPDATE end_users SET merged_into_user_id = %s, status = 'unsubscribed' WHERE id = %s",
            (target_user_id, source_user_id),
        )
        await cur.execute(
            "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
            (target_user_id,),
        )
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (target_user_id,))
        return await cur.fetchone()


async def find_similar_account_suggestions(user: dict, limit: int = 3) -> list[dict]:
    city = str(user.get("city") or "").strip()
    current_platform = str(user.get("current_platform") or user.get("platform") or "web")
    birth_date = str(user.get("birth_date") or "").strip()
    age = profile_age(user)
    if not user.get("id") or not city or (not birth_date and age is None):
        return []

    where = [
        "u.id <> %s",
        "u.merged_into_user_id IS NULL",
        "COALESCE(pa.platform, u.platform) <> %s",
        "LOWER(TRIM(u.city)) = LOWER(TRIM(%s))",
    ]
    params: list[object] = [int(user["id"]), current_platform, city]

    if user.get("manager_id"):
        where.append("u.manager_id = %s")
        params.append(int(user["manager_id"]))
    elif user.get("reseller_id"):
        where.append("u.reseller_id = %s")
        params.append(int(user["reseller_id"]))
    else:
        return []

    if birth_date:
        if age is not None:
            where.append("(u.birth_date = %s OR (u.birth_date IS NULL AND u.age_years = %s))")
            params.extend([birth_date, age])
        else:
            where.append("u.birth_date = %s")
            params.append(birth_date)
    elif age is not None:
        where.append("u.birth_date IS NULL AND u.age_years = %s")
        params.append(age)

    gender = str(user.get("gender") or "")
    if gender and gender != "prefer_not_to_say":
        where.append("(u.gender = %s OR u.gender IS NULL OR u.gender = 'prefer_not_to_say')")
        params.append(gender)

    limit = max(1, min(5, limit))
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT COALESCE(pa.platform, u.platform) AS linked_platform,
                   COUNT(DISTINCT u.id) AS matches_count
            FROM end_users u
            LEFT JOIN platform_accounts pa ON pa.end_user_id = u.id
            WHERE {' AND '.join(where)}
            GROUP BY linked_platform
            ORDER BY FIELD(linked_platform, 'telegram', 'VK', 'OK', 'web', 'MAX'), linked_platform
            LIMIT {limit}
            """,
            tuple(params),
        )
        rows = await cur.fetchall()

    suggestions: list[dict] = []
    for row in rows:
        platform = str(row["linked_platform"] or "")
        if platform == current_platform or platform == "all":
            continue
        suggestions.append({
            "platform": platform,
            "platform_label": platform_label(platform),
            "matches": int(row["matches_count"] or 0),
        })
    return suggestions


async def account_suggestions_payload(user: dict) -> dict:
    suggestions = await find_similar_account_suggestions(user)
    payload = {"suggestions": suggestions}
    if suggestions:
        payload["linking"] = account_link_payload(user)
    return payload


async def fill_user_names_if_missing(cur, user: dict, first_name: str | None, last_name: str | None) -> dict:
    first_name = str(first_name or "").strip()
    last_name = str(last_name or "").strip()
    assignments: list[str] = []
    params: list[object] = []

    if first_name and not str(user.get("first_name") or "").strip():
        assignments.append("first_name = %s")
        params.append(first_name)
    if last_name and not str(user.get("last_name") or "").strip():
        assignments.append("last_name = %s")
        params.append(last_name)
    if not assignments:
        return user

    params.append(user["id"])
    await cur.execute(
        f"UPDATE end_users SET {', '.join(assignments)} WHERE id = %s",
        tuple(params),
    )
    await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (user["id"],))
    return await cur.fetchone() or user


async def attach_referral_if_missing(user: dict, referral_code: str | None, platform: str) -> dict:
    normalized_code = normalize_referral_code(referral_code)
    if not normalized_code or user.get("reseller_id") or user.get("manager_id"):
        user["current_platform"] = platform
        return user

    referral = await resolve_referral(normalized_code)
    if not referral.get("reseller_id") and not referral.get("manager_id"):
        user["current_platform"] = platform
        return user

    async with cursor() as cur:
        await cur.execute(
            """
            UPDATE end_users
            SET reseller_id = %s, manager_id = %s, referral_code_used = %s
            WHERE id = %s AND reseller_id IS NULL AND manager_id IS NULL
            """,
            (referral["reseller_id"], referral["manager_id"], normalized_code, user["id"]),
        )
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (user["id"],))
        updated = await cur.fetchone()

    await increment_registration(normalized_code, platform)
    result = updated or user
    result["current_platform"] = platform
    return result


async def staff_platform_account_exists(platform: str, platform_user_id: str) -> bool:
    field = {
        "telegram": "telegram_id",
        "VK": "vk_id",
        "MAX": "max_id",
    }.get(platform)
    if field is None:
        return False

    normalized_id = platform_user_id.strip().lower().removeprefix("id")
    async with cursor() as cur:
        await cur.execute(
            f"SELECT COUNT(*) AS total FROM managers WHERE {field} IS NOT NULL AND {field} <> '' AND REPLACE(LOWER({field}), 'id', '') = %s",
            (normalized_id,),
        )
        manager = await cur.fetchone()
        if manager and int(manager["total"]) > 0:
            return True

        await cur.execute(
            f"""
            SELECT COUNT(*) AS total
            FROM admin_users
            WHERE role IN ('superadmin', 'reseller', 'manager')
              AND {field} IS NOT NULL
              AND {field} <> ''
              AND REPLACE(LOWER({field}), 'id', '') = %s
            """,
            (normalized_id,),
        )
        admin = await cur.fetchone()
        return bool(admin and int(admin["total"]) > 0)


async def get_or_create_user(
    platform: str,
    platform_user_id: str,
    username: str | None = None,
    first_name: str | None = None,
    last_name: str | None = None,
    referral_code: str | None = None,
    link_token: str | None = None,
) -> dict:
    if await staff_platform_account_exists(platform, platform_user_id):
        raise StaffAccountError("staff account cannot be registered as an end user")

    link_target_user_id = parse_account_link_token(link_token)

    async with cursor() as cur:
        await cur.execute(
            """
            SELECT u.*
            FROM platform_accounts pa
            JOIN end_users u ON u.id = pa.end_user_id
            WHERE pa.platform = %s AND pa.platform_user_id = %s
            LIMIT 1
            """,
            (platform, platform_user_id),
        )
        existing = await cur.fetchone()
        if existing:
            if link_target_user_id and int(existing["id"]) != link_target_user_id:
                linked = await link_existing_user_to_target(link_target_user_id, int(existing["id"]))
                if linked:
                    linked = await fill_user_names_if_missing(cur, linked, first_name, last_name)
                    return await attach_referral_if_missing(linked, referral_code, platform)
            await cur.execute(
                """
                UPDATE platform_accounts
                SET username = COALESCE(NULLIF(%s, ''), username),
                    first_name = COALESCE(NULLIF(%s, ''), first_name),
                    last_name = COALESCE(NULLIF(%s, ''), last_name),
                    display_name = COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', %s, %s)), ''),
                        display_name
                    )
                WHERE platform = %s AND platform_user_id = %s
                """,
                (
                    username,
                    first_name,
                    last_name,
                    first_name,
                    last_name,
                    platform,
                    platform_user_id,
                ),
            )
            await cur.execute(
                "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
                (existing["id"],),
            )
            existing = await fill_user_names_if_missing(cur, existing, first_name, last_name)
            return await attach_referral_if_missing(existing, referral_code, platform)

        await cur.execute(
            "SELECT * FROM end_users WHERE platform = %s AND platform_user_id = %s LIMIT 1",
            (platform, platform_user_id),
        )
        legacy = await cur.fetchone()
        if legacy:
            if link_target_user_id and int(legacy["id"]) != link_target_user_id:
                linked = await link_existing_user_to_target(link_target_user_id, int(legacy["id"]))
                if linked:
                    linked = await fill_user_names_if_missing(cur, linked, first_name, last_name)
                    return await attach_referral_if_missing(linked, referral_code, platform)
            await cur.execute(
                """
                INSERT INTO platform_accounts (
                    end_user_id, platform, platform_user_id, username,
                    first_name, last_name, display_name
                )
                VALUES (%s, %s, %s, %s, %s, %s, NULLIF(TRIM(CONCAT_WS(' ', %s, %s)), ''))
                ON DUPLICATE KEY UPDATE
                    end_user_id = VALUES(end_user_id),
                    username = COALESCE(NULLIF(VALUES(username), ''), username),
                    first_name = COALESCE(NULLIF(VALUES(first_name), ''), first_name),
                    last_name = COALESCE(NULLIF(VALUES(last_name), ''), last_name),
                    display_name = COALESCE(NULLIF(VALUES(display_name), ''), display_name)
                """,
                (
                    legacy["id"],
                    platform,
                    platform_user_id,
                    username,
                    first_name,
                    last_name,
                    first_name,
                    last_name,
                ),
            )
            await cur.execute(
                "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
                (legacy["id"],),
            )
            legacy = await fill_user_names_if_missing(cur, legacy, first_name, last_name)
            return await attach_referral_if_missing(legacy, referral_code, platform)

        if link_target_user_id:
            await cur.execute(
                "SELECT * FROM end_users WHERE id = %s AND merged_into_user_id IS NULL LIMIT 1",
                (link_target_user_id,),
            )
            target_user = await cur.fetchone()
            if target_user:
                await cur.execute(
                    """
                    INSERT INTO platform_accounts (
                        end_user_id, platform, platform_user_id, username,
                        first_name, last_name, display_name
                    )
                    VALUES (%s, %s, %s, %s, %s, %s, NULLIF(TRIM(CONCAT_WS(' ', %s, %s)), ''))
                    ON DUPLICATE KEY UPDATE
                        end_user_id = VALUES(end_user_id),
                        username = COALESCE(NULLIF(VALUES(username), ''), username),
                        first_name = COALESCE(NULLIF(VALUES(first_name), ''), first_name),
                        last_name = COALESCE(NULLIF(VALUES(last_name), ''), last_name),
                        display_name = COALESCE(NULLIF(VALUES(display_name), ''), display_name)
                    """,
                    (
                        target_user["id"],
                        platform,
                        platform_user_id,
                        username,
                        first_name,
                        last_name,
                        first_name,
                        last_name,
                    ),
                )
                await cur.execute(
                    "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
                    (target_user["id"],),
                )
                target_user = await fill_user_names_if_missing(cur, target_user, first_name, last_name)
                return await attach_referral_if_missing(target_user, referral_code, platform)

    normalized_referral_code = normalize_referral_code(referral_code)
    referral = await resolve_referral(normalized_referral_code)
    if not referral["owner_type"]:
        normalized_referral_code = None

    async with cursor() as cur:
        await cur.execute(
            """
            INSERT INTO end_users (
                reseller_id, manager_id, platform, platform_user_id, username,
                first_name, last_name, referral_code_used, last_activity_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW())
            """,
            (
                referral["reseller_id"],
                referral["manager_id"],
                platform,
                platform_user_id,
                username,
                first_name,
                last_name,
                normalized_referral_code,
            ),
        )
        user_id = cur.lastrowid
        await cur.execute(
            """
            INSERT INTO platform_accounts (
                end_user_id, platform, platform_user_id, username,
                first_name, last_name, display_name
            )
            VALUES (%s, %s, %s, %s, %s, %s, NULLIF(TRIM(CONCAT_WS(' ', %s, %s)), ''))
            """,
            (
                user_id,
                platform,
                platform_user_id,
                username,
                first_name,
                last_name,
                first_name,
                last_name,
            ),
        )
        await cur.execute(
            """
            INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
            VALUES ('system', NULL, 'create_user', 'end_users', %s, JSON_OBJECT('platform', %s, 'referral_code', %s))
            """,
            (user_id, platform, normalized_referral_code),
        )
        await cur.execute("SELECT * FROM end_users WHERE id = %s", (user_id,))
        user = await cur.fetchone()

    await increment_registration(normalized_referral_code, platform)
    user["current_platform"] = platform
    return user


async def get_user_profile(end_user_id: int) -> dict | None:
    async with cursor() as cur:
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (end_user_id,))
        return await cur.fetchone()


async def ensure_platform_account(
    end_user_id: int,
    platform: str,
    platform_user_id: str,
    username: str | None = None,
) -> None:
    async with cursor() as cur:
        await cur.execute(
            """
            INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE end_user_id = VALUES(end_user_id), username = VALUES(username)
            """,
            (end_user_id, platform, platform_user_id, username),
        )
