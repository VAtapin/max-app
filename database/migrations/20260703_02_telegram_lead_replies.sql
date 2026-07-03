SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE leads
  ADD COLUMN request_type VARCHAR(50) NOT NULL DEFAULT 'consultation' AFTER product_id;

UPDATE leads
SET request_type = CASE
  WHEN product_id IS NOT NULL THEN 'product'
  ELSE 'consultation'
END
WHERE request_type = 'consultation';

ALTER TABLE lead_responses
  ADD COLUMN response_source ENUM('admin', 'telegram') NOT NULL DEFAULT 'admin' AFTER admin_user_id,
  ADD COLUMN telegram_chat_id VARCHAR(100) NULL AFTER response_source,
  ADD COLUMN telegram_message_id BIGINT UNSIGNED NULL AFTER telegram_chat_id,
  ADD COLUMN read_at DATETIME NULL AFTER sent_at,
  ADD UNIQUE KEY uq_lead_response_telegram_message (telegram_chat_id, telegram_message_id);

ALTER TABLE consultant_notifications
  MODIFY COLUMN manager_id BIGINT UNSIGNED NULL,
  ADD COLUMN reseller_id BIGINT UNSIGNED NULL AFTER manager_id,
  ADD COLUMN lead_id BIGINT UNSIGNED NULL AFTER end_user_id,
  ADD COLUMN source_platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NULL AFTER notification_type,
  ADD COLUMN telegram_chat_id VARCHAR(100) NULL AFTER delivery_error,
  ADD COLUMN telegram_message_id BIGINT UNSIGNED NULL AFTER telegram_chat_id,
  ADD UNIQUE KEY uq_consultant_notification_reseller_event (reseller_id, event_key),
  ADD UNIQUE KEY uq_consultant_notification_telegram_message (telegram_chat_id, telegram_message_id),
  ADD INDEX idx_consultant_notifications_lead (lead_id),
  ADD INDEX idx_consultant_notifications_reseller (reseller_id, is_read, created_at),
  ADD CONSTRAINT fk_consultant_notifications_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_consultant_notifications_lead
    FOREIGN KEY (lead_id) REFERENCES leads(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
