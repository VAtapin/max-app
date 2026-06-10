from bot.core.messages import MEDICAL_DISCLAIMER
from bot.db.mysql import cursor


async def build_recommendations(end_user_id: int, test_session_id: int) -> list[dict]:
    async with cursor() as cur:
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

        recommendations = []
        for item in scores:
            product_id = item["product_id"]
            if not product_id and item["category_id"]:
                await cur.execute(
                    """
                    SELECT id FROM products
                    WHERE category_id = %s AND is_active = 1
                    ORDER BY sort_order, id
                    LIMIT 1
                    """,
                    (item["category_id"],),
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
                    "Рекомендация сформирована по ответам теста. " + MEDICAL_DISCLAIMER,
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
