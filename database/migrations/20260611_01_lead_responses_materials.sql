ALTER TABLE content_posts
  ADD COLUMN IF NOT EXISTS content_type ENUM('article', 'image', 'pdf', 'video', 'link') NOT NULL DEFAULT 'article' AFTER id,
  ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER image_path,
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL AFTER attachment_path,
  ADD COLUMN IF NOT EXISTS button_text VARCHAR(100) NULL AFTER video_url,
  ADD COLUMN IF NOT EXISTS button_url VARCHAR(255) NULL AFTER button_text;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS document_path VARCHAR(255) NULL AFTER image_path,
  ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL AFTER document_path;

CREATE TABLE IF NOT EXISTS lead_responses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  content_post_id BIGINT UNSIGNED NULL,
  test_id BIGINT UNSIGNED NULL,
  platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  message_text TEXT NULL,
  attachment_path VARCHAR(255) NULL,
  external_url VARCHAR(255) NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lead_responses_lead_id (lead_id),
  INDEX idx_lead_responses_admin_user_id (admin_user_id),
  INDEX idx_lead_responses_status (status),
  CONSTRAINT fk_lead_responses_lead
    FOREIGN KEY (lead_id) REFERENCES leads(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_lead_responses_admin
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_lead_responses_content
    FOREIGN KEY (content_post_id) REFERENCES content_posts(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_lead_responses_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
