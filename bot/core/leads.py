from bot.db.mysql import cursor


async def create_lead(user: dict, message: str, product_id: int | None = None) -> int:
    async with cursor() as cur:
        await cur.execute(
            """
            INSERT INTO leads (end_user_id, manager_id, reseller_id, product_id, source_platform, message)
            VALUES (%s, %s, %s, %s, %s, %s)
            """,
            (
                user["id"],
                user.get("manager_id"),
                user.get("reseller_id"),
                product_id,
                user.get("current_platform", user["platform"]),
                message,
            ),
        )
        lead_id = cur.lastrowid
        await cur.execute(
            """
            INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id)
            VALUES ('end_user', %s, 'create_lead', 'leads', %s)
            """,
            (user["id"], lead_id),
        )
        return lead_id
