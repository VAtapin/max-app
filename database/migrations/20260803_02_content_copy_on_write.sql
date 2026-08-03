ALTER TABLE product_categories
  ADD COLUMN IF NOT EXISTS source_category_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER source_category_id,
  ADD INDEX IF NOT EXISTS idx_product_categories_source_category_id (source_category_id),
  ADD UNIQUE INDEX IF NOT EXISTS uq_product_categories_owner_source_clone (owner_type, owner_id, source_category_id);

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS source_product_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER source_product_id,
  ADD INDEX IF NOT EXISTS idx_products_source_product_id (source_product_id),
  ADD UNIQUE INDEX IF NOT EXISTS uq_products_owner_source_clone (owner_type, owner_id, source_product_id);

ALTER TABLE tests
  ADD COLUMN IF NOT EXISTS source_test_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER source_test_id,
  ADD INDEX IF NOT EXISTS idx_tests_source_test_id (source_test_id),
  ADD UNIQUE INDEX IF NOT EXISTS uq_tests_owner_source_clone (owner_type, owner_id, source_test_id);

ALTER TABLE content_posts
  ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER source_content_post_id;

ALTER TABLE broadcasts
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin', 'reseller', 'manager') NULL AFTER id,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NULL AFTER owner_type,
  ADD COLUMN IF NOT EXISTS source_broadcast_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER source_broadcast_id,
  ADD INDEX IF NOT EXISTS idx_broadcasts_owner (owner_type, owner_id),
  ADD INDEX IF NOT EXISTS idx_broadcasts_source_broadcast_id (source_broadcast_id),
  ADD UNIQUE INDEX IF NOT EXISTS uq_broadcasts_owner_source_clone (owner_type, owner_id, source_broadcast_id);

UPDATE broadcasts
SET owner_type = 'manager', owner_id = target_manager_id
WHERE owner_type IS NULL
  AND target_type = 'manager'
  AND target_manager_id IS NOT NULL;

UPDATE broadcasts
SET owner_type = 'reseller', owner_id = target_reseller_id
WHERE owner_type IS NULL
  AND target_type = 'reseller'
  AND target_reseller_id IS NOT NULL;
