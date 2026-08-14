ALTER TABLE messaging_integrations
  ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER callback_last_error;

UPDATE messaging_integrations
SET is_default = 1
WHERE id = (
    SELECT id FROM (
      SELECT id
      FROM messaging_integrations
      WHERE platform = 'VK' AND external_id = '100622406' AND is_active = 1
      ORDER BY id
      LIMIT 1
    ) standard_group
  )
  AND NOT EXISTS (
    SELECT 1
    FROM (SELECT id FROM messaging_integrations WHERE platform = 'VK' AND is_default = 1 LIMIT 1) defaults_found
  );

CREATE TABLE IF NOT EXISTS vk_message_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  platform_account_id BIGINT UNSIGNED NOT NULL,
  integration_id BIGINT UNSIGNED NOT NULL,
  group_id VARCHAR(190) NOT NULL,
  status ENUM('pending', 'allowed', 'denied') NOT NULL DEFAULT 'pending',
  request_key_hash CHAR(64) NULL,
  request_expires_at DATETIME NULL,
  requested_at DATETIME NULL,
  allowed_at DATETIME NULL,
  denied_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vk_message_permission_user_group (end_user_id, group_id),
  UNIQUE KEY uq_vk_message_permission_key (request_key_hash),
  INDEX idx_vk_message_permission_account (platform_account_id, status),
  INDEX idx_vk_message_permission_integration (integration_id, status),
  CONSTRAINT fk_vk_message_permission_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_vk_message_permission_account
    FOREIGN KEY (platform_account_id) REFERENCES platform_accounts(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_vk_message_permission_integration
    FOREIGN KEY (integration_id) REFERENCES messaging_integrations(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
