SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE products
  ADD COLUMN allowed_claims TEXT NULL AFTER contraindications,
  ADD COLUMN source_urls TEXT NULL AFTER allowed_claims,
  ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER source_urls,
  ADD COLUMN content_status ENUM('draft','review','approved') NOT NULL DEFAULT 'draft' AFTER ai_enabled,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER content_status,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
  ADD COLUMN next_review_at DATE NULL AFTER reviewed_at,
  ADD INDEX idx_products_ai_content (ai_enabled, content_status, next_review_at);

ALTER TABLE test_scale_results
  ADD COLUMN exclusions_text TEXT NULL AFTER advice_text,
  ADD COLUMN escalation_text TEXT NULL AFTER exclusions_text,
  ADD COLUMN source_urls TEXT NULL AFTER escalation_text,
  ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER source_urls,
  ADD COLUMN content_status ENUM('draft','review','approved') NOT NULL DEFAULT 'draft' AFTER ai_enabled,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER content_status,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
  ADD COLUMN next_review_at DATE NULL AFTER reviewed_at,
  ADD INDEX idx_scale_results_ai_content (ai_enabled, content_status, next_review_at);

ALTER TABLE test_results
  ADD COLUMN exclusions_text TEXT NULL AFTER advice_text,
  ADD COLUMN escalation_text TEXT NULL AFTER exclusions_text,
  ADD COLUMN source_urls TEXT NULL AFTER escalation_text,
  ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER source_urls,
  ADD COLUMN content_status ENUM('draft','review','approved') NOT NULL DEFAULT 'draft' AFTER ai_enabled,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER content_status,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
  ADD COLUMN next_review_at DATE NULL AFTER reviewed_at,
  ADD INDEX idx_test_results_ai_content (ai_enabled, content_status, next_review_at);

ALTER TABLE ai_knowledge_entries
  ADD COLUMN source_key VARCHAR(500) NULL AFTER source_id,
  ADD COLUMN source_url VARCHAR(500) NULL AFTER source_key,
  ADD COLUMN content_hash CHAR(64) NULL AFTER source_url,
  ADD COLUMN last_synced_at DATETIME NULL AFTER content_hash,
  ADD COLUMN next_review_at DATE NULL AFTER approved_at,
  ADD UNIQUE KEY uq_ai_knowledge_source_key (owner_type, owner_id, source_type, source_key(190)),
  ADD INDEX idx_ai_knowledge_review (is_active, is_approved, next_review_at);

CREATE TABLE IF NOT EXISTS ai_recommendation_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scale_result_id BIGINT UNSIGNED NULL,
  test_result_id BIGINT UNSIGNED NULL,
  target_type ENUM('product','content') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  rule_type ENUM('include','exclude') NOT NULL DEFAULT 'include',
  rationale TEXT NULL,
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ai_rule_scale (scale_result_id, is_active, is_approved),
  INDEX idx_ai_rule_test (test_result_id, is_active, is_approved),
  INDEX idx_ai_rule_target (target_type, target_id, rule_type),
  CONSTRAINT fk_ai_rule_scale_result FOREIGN KEY (scale_result_id) REFERENCES test_scale_results(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ai_rule_test_result FOREIGN KEY (test_result_id) REFERENCES test_results(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ai_rule_approved_by FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_rule_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_conversation_scenarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin','reseller','manager') NOT NULL DEFAULT 'superadmin',
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  event_key VARCHAR(100) NOT NULL,
  channel ENUM('any','admin','web','telegram','VK','OK','MAX') NOT NULL DEFAULT 'any',
  audience ENUM('admin','client') NOT NULL DEFAULT 'client',
  title VARCHAR(190) NOT NULL,
  template_text MEDIUMTEXT NOT NULL,
  allowed_variables JSON NULL,
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ai_scenario_lookup (owner_type, owner_id, event_key, channel, audience, is_active, is_approved),
  CONSTRAINT fk_ai_scenario_approved_by FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_scenario_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_channel_media_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform ENUM('telegram','VK','OK','MAX','web') NOT NULL,
  delivery_mode ENUM('native_video','video_message','link') NOT NULL DEFAULT 'native_video',
  max_file_bytes BIGINT UNSIGNED NOT NULL DEFAULT 20971520,
  max_duration_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  allowed_mime_types VARCHAR(500) NOT NULL DEFAULT 'video/mp4',
  fallback_mode ENUM('native_video','link','text') NOT NULL DEFAULT 'link',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_channel_media_platform (platform),
  CONSTRAINT fk_ai_channel_media_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ai_channel_media_rules (platform, delivery_mode, max_file_bytes, max_duration_seconds, allowed_mime_types, fallback_mode) VALUES
  ('telegram', 'native_video', 20971520, 60, 'video/mp4', 'link'),
  ('VK', 'native_video', 20971520, 60, 'video/mp4', 'link'),
  ('OK', 'native_video', 20971520, 60, 'video/mp4', 'link'),
  ('MAX', 'native_video', 20971520, 60, 'video/mp4', 'link'),
  ('web', 'native_video', 52428800, 120, 'video/mp4', 'link')
ON DUPLICATE KEY UPDATE platform = VALUES(platform);

INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('ai.retention.conversations_days', '365', 'Срок хранения AI-диалогов и сообщений'),
  ('ai.retention.drafts_days', '180', 'Срок хранения архивных AI-черновиков'),
  ('ai.retention.failed_jobs_days', '30', 'Срок хранения завершённых с ошибкой AI-заданий'),
  ('ai.retention.ready_media_days', '0', 'Срок хранения готовых AI-аудио и видео; 0 означает бессрочно'),
  ('ai.retention.usage_days', '1095', 'Срок хранения детальных событий использования AI'),
  ('ai.docs_sync_enabled', '1', 'Разрешить синхронизацию утверждённых страниц Docsify в базу знаний AI')
ON DUPLICATE KEY UPDATE description = VALUES(description);
