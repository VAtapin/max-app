-- Scope catalog/content records by owner and store channel tokens per reseller/manager.
ALTER TABLE test_answers
  ADD COLUMN IF NOT EXISTS product_id BIGINT UNSIGNED NULL AFTER category_id;

ALTER TABLE product_categories
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin', 'reseller', 'manager') NULL AFTER description,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NULL AFTER owner_type;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin', 'reseller', 'manager') NULL AFTER category_id,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NULL AFTER owner_type;

ALTER TABLE tests
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin', 'reseller', 'manager') NULL AFTER category_id,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NULL AFTER owner_type;

ALTER TABLE content_posts
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin', 'reseller', 'manager') NULL AFTER category_id,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NULL AFTER owner_type;

ALTER TABLE broadcasts
  ADD COLUMN IF NOT EXISTS owner_type ENUM('superadmin', 'reseller', 'manager') NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS owner_id BIGINT UNSIGNED NULL AFTER owner_type;

CREATE TABLE IF NOT EXISTS messaging_integrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller', 'manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  platform ENUM('vk', 'telegram', 'max') NOT NULL,
  title VARCHAR(190) NOT NULL,
  external_id VARCHAR(190) NULL,
  access_token TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_messaging_integrations_owner (owner_type, owner_id),
  INDEX idx_messaging_integrations_platform (platform, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
