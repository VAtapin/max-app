from bot.core.client_journey import set_client_stage
from bot.db.mysql import cursor


REQUEST_TYPES = {"consultation", "product", "test_result", "cashback", "cooperation", "other"}


async def create_lead(
    user: dict,
    message: str,
    product_id: int | None = None,
    product_variant_id: int | None = None,
    recommendation_id: int | None = None,
    request_type: str = "consultation",
) -> int:
    if product_id:
        request_type = "product"
    elif request_type not in REQUEST_TYPES:
        request_type = "consultation"

    async with cursor() as cur:
        await cur.execute(
            """
            INSERT INTO leads (
                end_user_id, manager_id, reseller_id, product_id, product_variant_id, recommendation_id,
                request_type, source_platform, message
            )
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """,
            (
                user["id"],
                user.get("manager_id"),
                user.get("reseller_id"),
                product_id,
                product_variant_id,
                recommendation_id,
                request_type,
                user.get("current_platform", user["platform"]),
                message,
            ),
        )
        lead_id = cur.lastrowid
        await cur.execute(
            """
            INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id)
            VALUES ('end_user', %s, 'create_lead', 'leads', %s)
            """,
            (user["id"], lead_id),
        )
    await set_client_stage(int(user["id"]), "consultation_requested")
    return lead_id
