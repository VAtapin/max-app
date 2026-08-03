from bot.core.content_scope import user_content_scope
from bot.db.mysql import cursor


async def list_categories(user: dict | None = None) -> list[dict]:
    scope_sql, params = user_content_scope("categories", user, "pc")
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT pc.id, pc.title, pc.slug, pc.description
            FROM product_categories pc
            WHERE pc.is_active = 1 AND {scope_sql}
            ORDER BY pc.sort_order, pc.title
            """,
            params,
        )
        return await cur.fetchall()


async def list_products(category_id: int | None = None, user: dict | None = None) -> list[dict]:
    scope_sql, scope_params = user_content_scope("products", user, "p")
    sql = f"""
        SELECT p.id, p.category_id, p.title, p.short_description, p.image_path, p.price
        FROM products p
        LEFT JOIN product_categories pc ON pc.id = p.category_id
        WHERE p.is_active = 1 AND {scope_sql}
    """
    params: tuple = scope_params
    if category_id:
        async with cursor() as cur:
            await cur.execute(
                "SELECT source_category_id FROM product_categories WHERE id = %s LIMIT 1",
                (category_id,),
            )
            category = await cur.fetchone()
        source_category_id = category.get("source_category_id") if category else None
        sql += " AND (p.category_id = %s OR pc.source_category_id = %s"
        params = (*params, category_id, category_id)
        if source_category_id:
            sql += " OR p.category_id = %s"
            params = (*params, source_category_id)
        sql += ")"
    sql += " ORDER BY p.sort_order, p.title"

    async with cursor() as cur:
        await cur.execute(sql, params)
        return await cur.fetchall()


async def get_product(product_id: int, user: dict | None = None) -> dict | None:
    scope_sql, params = user_content_scope("products", user, "p")
    async with cursor() as cur:
        await cur.execute(
            f"SELECT p.* FROM products p WHERE p.id = %s AND p.is_active = 1 AND {scope_sql} LIMIT 1",
            (product_id, *params),
        )
        return await cur.fetchone()
