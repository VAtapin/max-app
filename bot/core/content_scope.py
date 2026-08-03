from __future__ import annotations


CONTENT_TABLES = {
    "categories": ("product_categories", "source_category_id", "is_deleted"),
    "products": ("products", "source_product_id", "is_deleted"),
    "tests": ("tests", "source_test_id", "is_deleted"),
    "content": ("content_posts", "source_content_post_id", "is_deleted"),
}


def _owner_clause(alias: str, owner_type: str | None, owner_id: int | None) -> tuple[str, tuple]:
    prefix = f"{alias}." if alias else ""
    if owner_type is None:
        return f"{prefix}owner_type IS NULL", ()
    return f"{prefix}owner_type = %s AND {prefix}owner_id = %s", (owner_type, owner_id)


def _owner_list_clause(alias: str, owners: list[tuple[str | None, int | None]]) -> tuple[str, tuple]:
    parts: list[str] = []
    params: list = []
    for owner_type, owner_id in owners:
        sql, values = _owner_clause(alias, owner_type, owner_id)
        parts.append(f"({sql})")
        params.extend(values)
    return "(" + " OR ".join(parts) + ")", tuple(params)


def user_content_scope(module: str, user: dict | None, alias: str = "") -> tuple[str, tuple]:
    config = CONTENT_TABLES.get(module)
    owners: list[tuple[str | None, int | None]] = [(None, None)]
    if user:
        if user.get("reseller_id"):
            owners.append(("reseller", int(user["reseller_id"])))
        if user.get("manager_id"):
            owners.append(("manager", int(user["manager_id"])))

    if not config:
        visible_sql, visible_params = _owner_list_clause(alias, owners)
        return visible_sql, visible_params

    table, source_column, deleted_column = config
    sql_alias = alias or table
    visible_sql, visible_params = _owner_list_clause(sql_alias, owners)
    prefix = f"{sql_alias}."
    visible_sql = f"({visible_sql}) AND {prefix}{deleted_column} = 0"

    override_owners = [owner for owner in owners if owner[0] is not None]
    if not override_owners:
        return visible_sql, visible_params

    clone_sql, clone_params = _owner_list_clause("cow_clone", override_owners)
    sql = f"""
        {visible_sql}
        AND NOT EXISTS (
            SELECT 1
            FROM {table} cow_clone
            WHERE cow_clone.id <> {prefix}id
              AND (
                  cow_clone.{source_column} = {prefix}id
                  OR ({prefix}{source_column} IS NOT NULL AND cow_clone.{source_column} = {prefix}{source_column})
              )
              AND {clone_sql}
              AND (
                  {prefix}owner_type IS NULL
                  OR ({prefix}owner_type = 'reseller' AND cow_clone.owner_type = 'manager')
              )
        )
    """
    return sql, (*visible_params, *clone_params)
