from bot.core.referrals import increment_registration, normalize_referral_code, resolve_referral
from bot.db.mysql import cursor


class StaffAccountError(RuntimeError):
    pass


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
) -> dict:
    if await staff_platform_account_exists(platform, platform_user_id):
        raise StaffAccountError("staff account cannot be registered as an end user")

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
            await cur.execute(
                "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
                (existing["id"],),
            )
            return await attach_referral_if_missing(existing, referral_code, platform)

        await cur.execute(
            "SELECT * FROM end_users WHERE platform = %s AND platform_user_id = %s LIMIT 1",
            (platform, platform_user_id),
        )
        legacy = await cur.fetchone()
        if legacy:
            await cur.execute(
                """
                INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE end_user_id = VALUES(end_user_id), username = VALUES(username)
                """,
                (legacy["id"], platform, platform_user_id, username),
            )
            await cur.execute(
                "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
                (legacy["id"],),
            )
            return await attach_referral_if_missing(legacy, referral_code, platform)

    normalized_referral_code = normalize_referral_code(referral_code)
    referral = await resolve_referral(normalized_referral_code)

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
            INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username)
            VALUES (%s, %s, %s, %s)
            """,
            (user_id, platform, platform_user_id, username),
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
