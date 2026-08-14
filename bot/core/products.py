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
        SELECT p.id, p.category_id, p.title, p.short_description,
               CASE WHEN p.image_review_status = 'rejected' THEN NULL ELSE p.image_path END image_path,
               p.price, p.catalog_sku,
               (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) primary_variant_id,
               (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) primary_sku
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


async def search_products(query: str, user: dict | None = None, limit: int = 10) -> list[dict]:
    value = " ".join(query.split()).strip()
    if not value:
        return []
    scope_sql, scope_params = user_content_scope("products", user, "p")
    like = f"%{value}%"
    async with cursor() as cur:
        await cur.execute(
            f"""
            SELECT DISTINCT p.id, p.title, p.short_description,
                   CASE WHEN p.image_review_status = 'rejected' THEN NULL ELSE p.image_path END image_path,
                   p.price, p.catalog_sku,
                   COALESCE(
                       (SELECT pvm.id FROM product_variants pvm WHERE pvm.product_id = p.id AND pvm.is_active = 1 AND pvm.sku = %s LIMIT 1),
                       (SELECT pv2.id FROM product_variants pv2 WHERE pv2.product_id = p.id AND pv2.is_active = 1 ORDER BY pv2.is_sample, pv2.sort_order, pv2.id LIMIT 1)
                   ) primary_variant_id,
                   COALESCE(
                       (SELECT pvm.sku FROM product_variants pvm WHERE pvm.product_id = p.id AND pvm.is_active = 1 AND pvm.sku = %s LIMIT 1),
                       (SELECT pv2.sku FROM product_variants pv2 WHERE pv2.product_id = p.id AND pv2.is_active = 1 ORDER BY pv2.is_sample, pv2.sort_order, pv2.id LIMIT 1)
                   ) primary_sku
            FROM products p
            LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
            WHERE p.is_active = 1 AND {scope_sql}
              AND (p.title LIKE %s OR p.catalog_sku = %s OR pv.sku = %s OR pv.title LIKE %s)
            ORDER BY (p.catalog_sku = %s OR pv.sku = %s) DESC, p.sort_order, p.title
            LIMIT %s
            """,
            (value, value, *scope_params, like, value, value, like, value, value, max(1, min(limit, 20))),
        )
        return await cur.fetchall()


async def get_product(product_id: int, user: dict | None = None) -> dict | None:
    scope_sql, params = user_content_scope("products", user, "p")
    async with cursor() as cur:
        await cur.execute(
            f"SELECT p.* FROM products p WHERE p.id = %s AND p.is_active = 1 AND {scope_sql} LIMIT 1",
            (product_id, *params),
        )
        return await cur.fetchone()


async def get_product_variant(product_id: int, variant_id: int) -> dict | None:
    if not variant_id:
        return None
    async with cursor() as cur:
        await cur.execute(
            "SELECT id, product_id, sku, title, volume_text, price FROM product_variants WHERE id = %s AND product_id = %s AND is_active = 1 LIMIT 1",
            (variant_id, product_id),
        )
        return await cur.fetchone()
