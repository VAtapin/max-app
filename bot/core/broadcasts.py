from bot.db.mysql import cursor


async def list_due_broadcasts() -> list[dict]:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT * FROM broadcasts
            WHERE status = 'scheduled'
              AND scheduled_at IS NOT NULL
              AND scheduled_at <= NOW()
            ORDER BY scheduled_at
            """
        )
        return await cur.fetchall()
