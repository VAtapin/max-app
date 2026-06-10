from bot.db.mysql import cursor


async def resolve_referral(referral_code: str | None) -> dict:
    if not referral_code:
        return {"reseller_id": None, "manager_id": None, "owner_type": None}

    async with cursor() as cur:
        await cur.execute(
            "SELECT id, reseller_id FROM managers WHERE referral_code = %s AND is_active = 1 LIMIT 1",
            (referral_code,),
        )
        manager = await cur.fetchone()
        if manager:
            return {
                "reseller_id": manager["reseller_id"],
                "manager_id": manager["id"],
                "owner_type": "manager",
            }

        await cur.execute(
            "SELECT id FROM resellers WHERE referral_code = %s AND is_active = 1 LIMIT 1",
            (referral_code,),
        )
        reseller = await cur.fetchone()
        if reseller:
            return {"reseller_id": reseller["id"], "manager_id": None, "owner_type": "reseller"}

    return {"reseller_id": None, "manager_id": None, "owner_type": None}


async def increment_registration(referral_code: str | None, platform: str) -> None:
    if not referral_code:
        return

    async with cursor() as cur:
        await cur.execute(
            """
            UPDATE referral_links
            SET registrations_count = registrations_count + 1
            WHERE referral_code = %s AND platform = %s
            """,
            (referral_code, platform),
        )
