import json

from bot.core.i18n import tr
from bot.core.content_scope import user_content_scope
from bot.db.mysql import cursor


SAFE_PRODUCT_SQL = """
    p.is_active = 1
    AND p.is_deleted = 0
    AND p.ai_enabled = 1
    AND p.content_status = 'approved'
    AND (
        p.product_kind NOT IN ('supplement', 'food')
        OR (
            p.safety_review_status = 'verified'
            AND NULLIF(p.composition, '') IS NOT NULL
            AND NULLIF(p.usage_text, '') IS NOT NULL
            AND NULLIF(p.warning_text, '') IS NOT NULL
            AND NULLIF(p.contraindications, '') IS NOT NULL
            AND NULLIF(p.allowed_claims, '') IS NOT NULL
            AND NULLIF(p.source_urls, '') IS NOT NULL
        )
    )
"""


async def apply_signal_recommendations(end_user_id: int, test_session_id: int) -> list[dict]:
    """Apply the same universal need-to-product matching used by the web API."""
    async with cursor() as cur:
        await cur.execute("SELECT * FROM end_users WHERE id = %s LIMIT 1", (end_user_id,))
        user = await cur.fetchone() or {}
        scope_sql, scope_params = user_content_scope("products", user, "p")
        await cur.execute(
            f"""
            SELECT DISTINCT r.*,
                   CASE WHEN p.id IS NULL THEN 0 ELSE 1 END product_is_available
            FROM ai_recommendation_rules r
            LEFT JOIN products p ON p.id = r.target_id
              AND r.target_type = 'product' AND {SAFE_PRODUCT_SQL} AND {scope_sql}
            WHERE r.is_active = 1 AND r.is_approved = 1 AND r.target_type = 'product'
              AND (
                r.scale_result_id IN (
                    SELECT uss.result_id
                    FROM user_test_scale_scores uss
                    JOIN test_scale_results sr ON sr.id = uss.result_id
                      AND sr.ai_enabled = 1 AND sr.content_status = 'approved'
                    WHERE uss.session_id = %s AND uss.result_id IS NOT NULL
                )
                OR r.test_result_id IN (
                    SELECT tr.id
                    FROM user_test_sessions uts
                    JOIN test_results tr ON tr.test_id = uts.test_id
                      AND tr.min_score <= uts.total_score AND tr.max_score >= uts.total_score
                      AND tr.ai_enabled = 1 AND tr.content_status = 'approved'
                    WHERE uts.id = %s
                )
              )
            ORDER BY r.rule_type = 'exclude', r.priority DESC, r.id
            """,
            (*scope_params, test_session_id, test_session_id),
        )
        rule_excluded: set[int] = set()
        for rule in await cur.fetchall():
            product_id = int(rule["target_id"])
            if rule["rule_type"] == "exclude":
                rule_excluded.add(product_id)
                await cur.execute(
                    "DELETE FROM recommendations WHERE test_session_id = %s AND product_id = %s",
                    (test_session_id, product_id),
                )
                continue
            if not int(rule.get("product_is_available") or 0):
                continue
            await cur.execute(
                "SELECT id FROM recommendations WHERE test_session_id = %s AND product_id = %s LIMIT 1",
                (test_session_id, product_id),
            )
            if not await cur.fetchone():
                await cur.execute(
                    """
                    INSERT INTO recommendations (end_user_id, test_session_id, product_id, reason_text, score)
                    VALUES (%s, %s, %s, %s, %s)
                    """,
                    (
                        end_user_id,
                        test_session_id,
                        product_id,
                        str(rule.get("rationale") or "").strip(),
                        int(rule.get("priority") or 0),
                    ),
                )

        await cur.execute(
            """
            SELECT signal_id, MAX(weight) weight
            FROM (
                SELECT trsl.signal_id, trsl.weight
                FROM user_test_sessions uts
                JOIN test_results tr ON tr.test_id = uts.test_id
                  AND tr.min_score <= uts.total_score AND tr.max_score >= uts.total_score
                JOIN test_result_signal_links trsl ON trsl.test_result_id = tr.id
                WHERE uts.id = %s
                UNION ALL
                SELECT srsl.signal_id, srsl.weight
                FROM user_test_scale_scores uss
                JOIN scale_result_signal_links srsl ON srsl.scale_result_id = uss.result_id
                WHERE uss.session_id = %s AND uss.result_id IS NOT NULL
            ) linked
            GROUP BY signal_id
            """,
            (test_session_id, test_session_id),
        )
        weights = {int(row["signal_id"]): int(row["weight"]) for row in await cur.fetchall()}

        await cur.execute(
            """
            SELECT CONCAT_WS(' ', t.title, tr.title, tr.summary_text, tr.advice_text,
                GROUP_CONCAT(CONCAT_WS(' ', ts.title, sr.title, sr.summary_text, sr.advice_text) SEPARATOR ' ')) signal_text
            FROM user_test_sessions uts
            JOIN tests t ON t.id = uts.test_id
            LEFT JOIN test_results tr ON tr.test_id = uts.test_id
              AND tr.min_score <= uts.total_score AND tr.max_score >= uts.total_score
            LEFT JOIN user_test_scale_scores uss ON uss.session_id = uts.id
            LEFT JOIN test_scale_results sr ON sr.id = uss.result_id
            LEFT JOIN test_scales ts ON ts.id = sr.scale_id
            WHERE uts.id = %s
            GROUP BY uts.id, t.title, tr.title, tr.summary_text, tr.advice_text
            """,
            (test_session_id,),
        )
        text_row = await cur.fetchone() or {}
        haystack = str(text_row.get("signal_text") or "").lower()
        if haystack:
            await cur.execute("SELECT id, keywords_json FROM recommendation_signals WHERE is_active = 1")
            for signal in await cur.fetchall():
                try:
                    keywords = json.loads(signal.get("keywords_json") or "[]")
                except (TypeError, ValueError):
                    keywords = []
                if any(str(keyword).strip().lower() in haystack for keyword in keywords if str(keyword).strip()):
                    signal_id = int(signal["id"])
                    weights[signal_id] = max(weights.get(signal_id, 0), 60)

        if not weights:
            return []

        signal_ids = list(weights)
        placeholders = ",".join(["%s"] * len(signal_ids))
        await cur.execute(
            f"""
            SELECT psl.product_id, psl.signal_id, psl.match_type, psl.weight,
                   psl.rationale, rs.title signal_title
            FROM product_signal_links psl
            JOIN recommendation_signals rs ON rs.id = psl.signal_id AND rs.is_active = 1
            JOIN products p ON p.id = psl.product_id AND {SAFE_PRODUCT_SQL} AND {scope_sql}
            WHERE psl.signal_id IN ({placeholders}) AND psl.is_approved = 1
            ORDER BY psl.match_type = 'exclude' DESC, psl.weight DESC, p.id
            LIMIT 100
            """,
            (*scope_params, *signal_ids),
        )
        candidates: dict[int, dict] = {}
        excluded: set[int] = set()
        for row in await cur.fetchall():
            product_id = int(row["product_id"])
            if product_id in rule_excluded:
                continue
            if row["match_type"] == "exclude":
                excluded.add(product_id)
                candidates.pop(product_id, None)
                continue
            if product_id in excluded:
                continue
            score = int(row["weight"]) + weights.get(int(row["signal_id"]), 0)
            if product_id not in candidates or score > int(candidates[product_id]["score"]):
                candidates[product_id] = {
                    "product_id": product_id,
                    "score": score,
                    "reason_text": str(row.get("rationale") or "").strip()
                    or f"Подходит к вашему запросу: {row['signal_title']}",
                }

        selected = sorted(candidates.values(), key=lambda item: int(item["score"]), reverse=True)[:3]
        for item in selected:
            await cur.execute(
                "SELECT id FROM recommendations WHERE test_session_id = %s AND product_id = %s LIMIT 1",
                (test_session_id, item["product_id"]),
            )
            if await cur.fetchone():
                continue
            await cur.execute(
                """
                INSERT INTO recommendations (end_user_id, test_session_id, product_id, reason_text, score)
                VALUES (%s, %s, %s, %s, %s)
                """,
                (end_user_id, test_session_id, item["product_id"], item["reason_text"], item["score"]),
            )
        return selected


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
                      AND {SAFE_PRODUCT_SQL}
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
            SELECT r.*, p.title AS product_title, p.short_description,
                   CASE WHEN p.image_review_status = 'rejected' THEN NULL ELSE p.image_path END image_path,
                   p.catalog_sku,
                   (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) primary_variant_id,
                   (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) primary_sku
            FROM recommendations r
            JOIN products p ON p.id = r.product_id AND """ + SAFE_PRODUCT_SQL + """
            WHERE r.end_user_id = %s
            ORDER BY r.score DESC, r.id DESC
            """,
            (end_user_id,),
        )
        return await cur.fetchall()
