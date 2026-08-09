ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin','reseller') NOT NULL DEFAULT 'superadmin' AFTER id,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER owner_type;

SET @old_slug_unique := (
  SELECT INDEX_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscription_plans'
    AND COLUMN_NAME = 'slug'
    AND NON_UNIQUE = 0
    AND INDEX_NAME <> 'uq_subscription_plans_owner_slug'
  GROUP BY INDEX_NAME
  HAVING COUNT(*) = 1
  LIMIT 1
);

SET @drop_slug_unique_sql := IF(
  @old_slug_unique IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE subscription_plans DROP INDEX `', REPLACE(@old_slug_unique, '`', '``'), '`')
);
PREPARE drop_slug_unique_stmt FROM @drop_slug_unique_sql;
EXECUTE drop_slug_unique_stmt;
DEALLOCATE PREPARE drop_slug_unique_stmt;

ALTER TABLE subscription_plans
  ADD UNIQUE INDEX IF NOT EXISTS uq_subscription_plans_owner_slug (owner_type, owner_id, slug),
  ADD INDEX IF NOT EXISTS idx_subscription_plans_owner (owner_type, owner_id);
