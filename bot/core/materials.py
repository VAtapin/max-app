from __future__ import annotations

from bot.db.mysql import cursor


async def ensure_manager_material_clones(user: dict) -> None:
    manager_id = user.get("manager_id")
    reseller_id = user.get("reseller_id")
    if not manager_id or not reseller_id:
        return

    async with cursor() as cur:
        await cur.execute(
            """
            INSERT IGNORE INTO content_posts (
                content_type, section_type, title, short_text, full_text,
                image_path, attachment_path, video_url, button_text, button_url,
                category_id, owner_type, owner_id, status, publish_at, created_by,
                source_content_post_id
            )
            SELECT source.content_type, source.section_type, source.title, source.short_text, source.full_text,
                   source.image_path, source.attachment_path, source.video_url, source.button_text, source.button_url,
                   source.category_id, 'manager', %s, source.status, source.publish_at, source.created_by,
                   source.id
            FROM content_posts source
            WHERE source.owner_type = 'reseller'
              AND source.owner_id = %s
              AND source.status <> 'hidden'
              AND NOT EXISTS (
                    SELECT 1
                    FROM content_posts clone
                    WHERE clone.owner_type = 'manager'
                      AND clone.owner_id = %s
                      AND clone.source_content_post_id = source.id
              )
            """,
            (manager_id, reseller_id, manager_id),
        )


def _scope_params(user: dict) -> tuple[str, tuple]:
    if user.get("manager_id"):
        return "(owner_type = 'manager' AND owner_id = %s)", (user["manager_id"],)

    if user.get("reseller_id"):
        return "(owner_type = 'reseller' AND owner_id = %s)", (user["reseller_id"],)

    return "1 = 0", ()


async def list_materials(user: dict, limit: int = 10) -> list[dict]:
    await ensure_manager_material_clones(user)
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
    await ensure_manager_material_clones(user)
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
