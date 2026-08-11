ALTER TABLE resellers
  ADD COLUMN source_end_user_id BIGINT UNSIGNED NULL AFTER id,
  ADD INDEX idx_resellers_source_end_user_id (source_end_user_id);

ALTER TABLE managers
  ADD COLUMN source_end_user_id BIGINT UNSIGNED NULL AFTER id,
  ADD INDEX idx_managers_source_end_user_id (source_end_user_id);

ALTER TABLE end_users
  ADD COLUMN referral_registered_at DATETIME NULL AFTER onboarding_completed_at,
  ADD INDEX idx_end_users_referral_registered_at (referral_registered_at);

ALTER TABLE user_test_sessions
  ADD COLUMN is_preview TINYINT(1) NOT NULL DEFAULT 0 AFTER result_summary,
  ADD INDEX idx_user_test_sessions_preview (is_preview, completed_at);

CREATE TABLE admin_web_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  web_user_id VARCHAR(100) NOT NULL,
  last_seen_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_web_accounts_web_user_id (web_user_id),
  INDEX idx_admin_web_accounts_admin_user_id (admin_user_id),
  INDEX idx_admin_web_accounts_active (revoked_at, last_seen_at),
  CONSTRAINT fk_admin_web_accounts_admin_user
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE resellers r
JOIN admin_users au ON au.reseller_id = r.id AND au.role = 'reseller'
JOIN platform_accounts pa
  ON (pa.platform = 'telegram' AND REPLACE(LOWER(au.telegram_id), 'id', '') = REPLACE(LOWER(pa.platform_user_id), 'id', ''))
  OR (pa.platform = 'VK' AND REPLACE(LOWER(au.vk_id), 'id', '') = REPLACE(LOWER(pa.platform_user_id), 'id', ''))
  OR (pa.platform = 'MAX' AND REPLACE(LOWER(au.max_id), 'id', '') = REPLACE(LOWER(pa.platform_user_id), 'id', ''))
SET r.source_end_user_id = pa.end_user_id
WHERE r.source_end_user_id IS NULL;

UPDATE managers m
JOIN admin_users au ON au.manager_id = m.id AND au.role = 'manager'
JOIN platform_accounts pa
  ON (pa.platform = 'telegram' AND REPLACE(LOWER(au.telegram_id), 'id', '') = REPLACE(LOWER(pa.platform_user_id), 'id', ''))
  OR (pa.platform = 'VK' AND REPLACE(LOWER(au.vk_id), 'id', '') = REPLACE(LOWER(pa.platform_user_id), 'id', ''))
  OR (pa.platform = 'MAX' AND REPLACE(LOWER(au.max_id), 'id', '') = REPLACE(LOWER(pa.platform_user_id), 'id', ''))
SET m.source_end_user_id = pa.end_user_id
WHERE m.source_end_user_id IS NULL;

UPDATE end_users
SET referral_registered_at = onboarding_completed_at
WHERE onboarding_completed_at IS NOT NULL
  AND referral_registered_at IS NULL;

UPDATE referral_links rl
SET registrations_count = (
  SELECT COUNT(*)
  FROM end_users eu
  WHERE eu.referral_code_used = rl.referral_code
    AND eu.referral_registered_at IS NOT NULL
    AND eu.merged_into_user_id IS NULL
    AND eu.platform = rl.platform
);
