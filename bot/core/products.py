from bot.db.mysql import cursor


async def list_categories() -> list[dict]:
    async with cursor() as cur:
        await cur.execute(
            "SELECT id, title, slug, description FROM product_categories WHERE is_active = 1 ORDER BY sort_order, title"
        )
        return await cur.fetchall()


async def list_products(category_id: int | None = None) -> list[dict]:
    sql = "SELECT id, category_id, title, short_description, image_path, price FROM products WHERE is_active = 1"
    params: tuple = ()
    if category_id:
        sql += " AND category_id = %s"
        params = (category_id,)
    sql += " ORDER BY sort_order, title"

    async with cursor() as cur:
        await cur.execute(sql, params)
        return await cur.fetchall()


async def get_product(product_id: int) -> dict | None:
    async with cursor() as cur:
        await cur.execute("SELECT * FROM products WHERE id = %s AND is_active = 1 LIMIT 1", (product_id,))
        return await cur.fetchone()
