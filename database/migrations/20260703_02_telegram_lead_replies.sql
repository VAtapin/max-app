SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE leads
  ADD COLUMN IF NOT EXISTS request_type VARCHAR(50) NOT NULL DEFAULT 'consultation' AFTER product_id;

UPDATE leads
SET request_type = CASE
  WHEN product_id IS NOT NULL THEN 'product'
  ELSE 'consultation'
END
WHERE request_type = 'consultation';

ALTER TABLE lead_responses
  ADD COLUMN IF NOT EXISTS response_source ENUM('admin', 'telegram') NOT NULL DEFAULT 'admin' AFTER admin_user_id,
  ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(100) NULL AFTER response_source,
  ADD COLUMN IF NOT EXISTS telegram_message_id BIGINT UNSIGNED NULL AFTER telegram_chat_id,
  ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER sent_at,
  ADD UNIQUE INDEX IF NOT EXISTS uq_lead_response_telegram_message (telegram_chat_id, telegram_message_id);

ALTER TABLE consultant_notifications
  MODIFY COLUMN manager_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS reseller_id BIGINT UNSIGNED NULL AFTER manager_id,
  ADD COLUMN IF NOT EXISTS lead_id BIGINT UNSIGNED NULL AFTER end_user_id,
  ADD COLUMN IF NOT EXISTS source_platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NULL AFTER notification_type,
  ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(100) NULL AFTER delivery_error,
  ADD COLUMN IF NOT EXISTS telegram_message_id BIGINT UNSIGNED NULL AFTER telegram_chat_id,
  ADD UNIQUE INDEX IF NOT EXISTS uq_consultant_notification_reseller_event (reseller_id, event_key),
  ADD UNIQUE INDEX IF NOT EXISTS uq_consultant_notification_telegram_message (telegram_chat_id, telegram_message_id),
  ADD INDEX IF NOT EXISTS idx_consultant_notifications_lead (lead_id),
  ADD INDEX IF NOT EXISTS idx_consultant_notifications_reseller (reseller_id, is_read, created_at);

SET @consultant_reseller_fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultant_notifications'
    AND CONSTRAINT_NAME = 'fk_consultant_notifications_reseller'
);
SET @consultant_reseller_fk_sql := IF(
  @consultant_reseller_fk_exists = 0,
  'ALTER TABLE consultant_notifications
     ADD CONSTRAINT fk_consultant_notifications_reseller
     FOREIGN KEY (reseller_id) REFERENCES resellers(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE consultant_reseller_fk_stmt FROM @consultant_reseller_fk_sql;
EXECUTE consultant_reseller_fk_stmt;
DEALLOCATE PREPARE consultant_reseller_fk_stmt;

SET @consultant_lead_fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultant_notifications'
    AND CONSTRAINT_NAME = 'fk_consultant_notifications_lead'
);
SET @consultant_lead_fk_sql := IF(
  @consultant_lead_fk_exists = 0,
  'ALTER TABLE consultant_notifications
     ADD CONSTRAINT fk_consultant_notifications_lead
     FOREIGN KEY (lead_id) REFERENCES leads(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE consultant_lead_fk_stmt FROM @consultant_lead_fk_sql;
EXECUTE consultant_lead_fk_stmt;
DEALLOCATE PREPARE consultant_lead_fk_stmt;
