CREATE TABLE IF NOT EXISTS admin_password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  requested_ip VARBINARY(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_password_resets_admin (admin_user_id),
  INDEX idx_admin_password_resets_expires_at (expires_at),
  CONSTRAINT fk_admin_password_resets_admin_user
    FOREIGN KEY (admin_user_id)
    REFERENCES admin_users (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
