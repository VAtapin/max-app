ALTER TABLE messaging_integrations
  ADD COLUMN IF NOT EXISTS callback_subscribed_at DATETIME NULL AFTER callback_secret;

CREATE TABLE IF NOT EXISTS ok_message_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  platform_account_id BIGINT UNSIGNED NOT NULL,
  integration_id BIGINT UNSIGNED NOT NULL,
  group_id VARCHAR(190) NOT NULL,
  status ENUM('pending', 'allowed', 'denied') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NULL,
  allowed_at DATETIME NULL,
  denied_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ok_message_permission_user_group (end_user_id, group_id),
  INDEX idx_ok_message_permission_account (platform_account_id, status),
  INDEX idx_ok_message_permission_integration (integration_id, status),
  CONSTRAINT fk_ok_message_permission_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ok_message_permission_account
    FOREIGN KEY (platform_account_id) REFERENCES platform_accounts(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ok_message_permission_integration
    FOREIGN KEY (integration_id) REFERENCES messaging_integrations(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
