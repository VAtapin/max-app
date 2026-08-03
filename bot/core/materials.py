from __future__ import annotations

from bot.core.content_scope import user_content_scope
from bot.db.mysql import cursor


async def list_materials(user: dict, limit: int = 10) -> list[dict]:
    scope_sql, params = user_content_scope("content", user, "c")
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT c.id, c.content_type, c.title, c.short_text, c.full_text, c.image_path,
                   c.attachment_path, c.video_url, c.button_text, c.button_url
            FROM content_posts c
            WHERE c.status = 'published'
              AND ({scope_sql})
            ORDER BY COALESCE(c.publish_at, c.created_at) DESC, c.id DESC
            LIMIT %s
            """,
            (*params, limit),
        )
        return await cur.fetchall()


async def get_material(material_id: int, user: dict) -> dict | None:
    scope_sql, params = user_content_scope("content", user, "c")
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT c.id, c.content_type, c.title, c.short_text, c.full_text, c.image_path,
                   c.attachment_path, c.video_url, c.button_text, c.button_url
            FROM content_posts c
            WHERE c.id = %s
              AND c.status = 'published'
              AND ({scope_sql})
            LIMIT 1
            """,
            (material_id, *params),
        )
        return await cur.fetchone()
