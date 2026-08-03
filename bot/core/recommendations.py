from bot.core.i18n import tr
from bot.core.content_scope import user_content_scope
from bot.db.mysql import cursor


async def build_recommendations(end_user_id: int, test_session_id: int) -> list[dict]:
    async with cursor() as cur:
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (end_user_id,))
        user = await cur.fetchone()
        await cur.execute(
            """
            SELECT
                ta.category_id,
                ta.tag_id,
                ta.product_id,
                SUM(uta.score) AS score
            FROM user_test_answers uta
            JOIN test_answers ta ON ta.id = uta.answer_id
            WHERE uta.session_id = %s
            GROUP BY ta.category_id, ta.tag_id, ta.product_id
            ORDER BY score DESC
            LIMIT 5
            """,
            (test_session_id,),
        )
        scores = await cur.fetchall()

        await cur.execute("DELETE FROM recommendations WHERE test_session_id = %s", (test_session_id,))

        recommendations = []
        for item in scores:
            product_id = item["product_id"]
            if not product_id and item["category_id"]:
                scope_sql, scope_params = user_content_scope("products", user, "p")
                await cur.execute(
                    "SELECT source_category_id FROM product_categories WHERE id = %s LIMIT 1",
                    (item["category_id"],),
                )
                category = await cur.fetchone()
                source_category_id = category.get("source_category_id") if category else None
                category_sql = "(p.category_id = %s OR pc.source_category_id = %s"
                category_params = [item["category_id"], item["category_id"]]
                if source_category_id:
                    category_sql += " OR p.category_id = %s"
                    category_params.append(source_category_id)
                category_sql += ")"
                await cur.execute(
                    f"""
                    SELECT p.id
                    FROM products p
                    LEFT JOIN product_categories pc ON pc.id = p.category_id
                    WHERE {category_sql}
                      AND p.is_active = 1
                      AND {scope_sql}
                    ORDER BY p.sort_order, p.id
                    LIMIT 1
                    """,
                    (*category_params, *scope_params),
                )
                product = await cur.fetchone()
                product_id = product["id"] if product else None

            await cur.execute(
                """
                INSERT INTO recommendations (
                    end_user_id, test_session_id, product_id, category_id, tag_id, reason_text, score
                ) VALUES (%s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    end_user_id,
                    test_session_id,
                    product_id,
                    item["category_id"],
                    item["tag_id"],
                    tr("recommendation.reason"),
                    item["score"],
                ),
            )
            recommendations.append({**item, "product_id": product_id})

        return recommendations


async def list_recommendations(end_user_id: int) -> list[dict]:
    async with cursor() as cur:
        await cur.execute(
            """
            SELECT r.*, p.title AS product_title, p.short_description
            FROM recommendations r
            LEFT JOIN products p ON p.id = r.product_id
            WHERE r.end_user_id = %s
            ORDER BY r.score DESC, r.id DESC
            """,
            (end_user_id,),
        )
        return await cur.fetchall()
