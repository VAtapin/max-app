from __future__ import annotations

from bot.db.mysql import cursor


def _scope_params(user: dict) -> tuple[str, tuple]:
    conditions = [
        "(owner_type IS NULL OR owner_type = 'superadmin')",
    ]
    params: list[object] = []

    if user.get("reseller_id"):
        conditions.append("(owner_type = 'reseller' AND owner_id = %s)")
        params.append(user["reseller_id"])
    if user.get("manager_id"):
        conditions.append("(owner_type = 'manager' AND owner_id = %s)")
        params.append(user["manager_id"])

    return " OR ".join(conditions), tuple(params)


async def list_materials(user: dict, limit: int = 10) -> list[dict]:
    scope_sql, params = _scope_params(user)
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT id, content_type, title, short_text, full_text, image_path,
                   attachment_path, video_url, button_text, button_url
            FROM content_posts
            WHERE status = 'published'
              AND ({scope_sql})
            ORDER BY COALESCE(publish_at, created_at) DESC, id DESC
            LIMIT %s
            """,
            (*params, limit),
        )
        return await cur.fetchall()


async def get_material(material_id: int, user: dict) -> dict | None:
    scope_sql, params = _scope_params(user)
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT id, content_type, title, short_text, full_text, image_path,
                   attachment_path, video_url, button_text, button_url
            FROM content_posts
            WHERE id = %s
              AND status = 'published'
              AND ({scope_sql})
            LIMIT 1
            """,
            (material_id, *params),
        )
        return await cur.fetchone()
