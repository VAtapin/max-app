from bot.core.referrals import increment_registration, resolve_referral
from bot.db.mysql import cursor


async def get_or_create_user(
    platform: str,
    platform_user_id: str,
    username: str | None = None,
    first_name: str | None = None,
    last_name: str | None = None,
    referral_code: str | None = None,
) -> dict:
    async with cursor() as cur:
        await cur.execute(
            "SELECT * FROM end_users WHERE platform = %s AND platform_user_id = %s LIMIT 1",
            (platform, platform_user_id),
        )
        existing = await cur.fetchone()
        if existing:
            await cur.execute(
                "UPDATE end_users SET last_activity_at = NOW() WHERE id = %s",
                (existing["id"],),
            )
            return existing

    referral = await resolve_referral(referral_code)

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
                referral_code,
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
            (user_id, platform, referral_code),
        )
        await cur.execute("SELECT * FROM end_users WHERE id = %s", (user_id,))
        user = await cur.fetchone()

    await increment_registration(referral_code, platform)
    return user


async def get_user_profile(end_user_id: int) -> dict | None:
    async with cursor() as cur:
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (end_user_id,))
        return await cur.fetchone()
