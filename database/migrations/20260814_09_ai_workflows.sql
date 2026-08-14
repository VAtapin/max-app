SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE subscription_plans
  ADD COLUMN ai_voice_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ai_personal_video_enabled,
  ADD COLUMN ai_voice_monthly_seconds INT UNSIGNED NULL AFTER ai_personal_video_monthly_seconds;

CREATE TABLE IF NOT EXISTS client_action_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  test_session_id BIGINT UNSIGNED NULL,
  owner_type ENUM('reseller','manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  status ENUM('draft','active','completed','cancelled') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_plan_user (end_user_id, status, starts_on),
  INDEX idx_client_plan_owner (owner_type, owner_id, status),
  CONSTRAINT fk_client_plan_user FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_client_plan_session FOREIGN KEY (test_session_id) REFERENCES user_test_sessions(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_client_plan_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_action_plan_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id BIGINT UNSIGNED NOT NULL,
  day_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  time_of_day ENUM('morning','day','evening','any') NOT NULL DEFAULT 'any',
  title VARCHAR(190) NOT NULL,
  instruction TEXT NULL,
  product_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(50) NULL,
  source_id BIGINT UNSIGNED NULL,
  is_completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client_plan_item (plan_id, day_number, is_completed),
  CONSTRAINT fk_client_plan_item_plan FOREIGN KEY (plan_id) REFERENCES client_action_plans(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_client_plan_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_action_suggestions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin','reseller','manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  end_user_id BIGINT UNSIGNED NULL,
  action_type ENUM('welcome','birthday','test_result','retest','inactive','plan_ending','follow_up','campaign') NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  due_on DATE NOT NULL,
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  title VARCHAR(190) NOT NULL,
  reason_text TEXT NULL,
  draft_text TEXT NULL,
  preferred_channel ENUM('telegram','VK','OK','MAX','web','any') NOT NULL DEFAULT 'any',
  status ENUM('pending','approved','done','dismissed') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_action_owner_event (owner_type, owner_id, event_key),
  INDEX idx_ai_action_today (owner_type, owner_id, due_on, status, priority),
  CONSTRAINT fk_ai_action_user FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ai_action_reviewer FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_content_drafts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin','reseller','manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  draft_type ENUM('post','video_script','greeting','campaign','product_description','voice_script') NOT NULL,
  audience VARCHAR(100) NULL,
  occasion VARCHAR(100) NULL,
  source_type VARCHAR(50) NULL,
  source_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  scheduled_for DATETIME NULL,
  status ENUM('draft','approved','used','archived') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ai_content_owner (owner_type, owner_id, status, draft_type),
  CONSTRAINT fk_ai_content_creator FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_voice_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller','manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  end_user_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  voice_mode ENUM('standard','cloned') NOT NULL DEFAULT 'standard',
  voice_id VARCHAR(190) NULL,
  script_text MEDIUMTEXT NOT NULL,
  script_hash CHAR(64) NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'disabled',
  output_path VARCHAR(500) NULL,
  duration_seconds DECIMAL(8,2) NULL,
  status ENUM('queued','processing','ready','failed','cancelled') NOT NULL DEFAULT 'queued',
  error_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX idx_ai_voice_owner (owner_type, owner_id, status, created_at),
  INDEX idx_ai_voice_hash (owner_type, owner_id, voice_mode, script_hash, status),
  CONSTRAINT fk_ai_voice_user FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_voice_conversation FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('ai.default_plan_days', '7', 'Стандартная продолжительность клиентского плана'),
  ('ai.retest_after_days', '30', 'Через сколько дней предлагать повторный чек-ап'),
  ('ai.inactive_after_days', '14', 'Через сколько дней без активности предложить связаться'),
  ('ai.voice_provider', 'disabled', 'Провайдер голосовых AI-сообщений')
ON DUPLICATE KEY UPDATE description = VALUES(description);

UPDATE content_posts
SET full_text = REPLACE(full_text, 'о формате работы, обучении и поддержке', 'о формате работы и поддержке')
WHERE full_text LIKE '%обучении и поддержке%';

INSERT INTO help_faq_sections
  (title, body, items_json, keywords, allowed_roles, page_context, ai_enabled, is_featured, is_active, sort_order)
VALUES
  ('Что сделать сегодня', 'На главной странице лидер и консультант видят клиентов, которым сейчас требуется внимание, и подготовленные тексты. Сообщение всегда проверяет человек перед отправкой.', JSON_ARRAY(
    'Система учитывает новых клиентов, дни рождения, завершённые и повторные чек-апы, неактивность и окончание персонального плана.',
    'Текст можно скопировать, исправить или отметить задачу выполненной.',
    'Автоматическая отправка без проверки владельца не выполняется.'
  ), 'сегодня задачи клиент поздравить повторный чек-ап сообщение', JSON_ARRAY('reseller','manager'), 'index.php', 1, 0, 1, 131),
  ('Персональный план клиента', 'После чек-апа SWPro может создать информационный план на 7, 14 или 30 дней из утверждённых советов и рекомендаций.', JSON_ARRAY(
    'Клиент отмечает выполненные пункты на странице «Сегодня для вас».',
    'План не является медицинским назначением.',
    'Лидер или консультант может обсудить и скорректировать план вместе с клиентом.'
  ), 'план 7 14 30 дней прогресс выполнить клиент', JSON_ARRAY('superadmin','reseller','manager'), NULL, 1, 0, 1, 132),
  ('Повторный чек-ап и сравнение', 'После установленного периода система предлагает пройти чек-ап ещё раз и показывает изменения по одинаковым шкалам.', JSON_ARRAY(
    'Положительная или отрицательная разница означает изменение баллов анкеты, а не медицинское улучшение или ухудшение.',
    'Сравниваются два последних завершённых прохождения одного чек-апа.',
    'Результат можно обсудить с закреплённым специалистом.'
  ), 'повторный чек-ап было стало сравнение результат', JSON_ARRAY('superadmin','reseller','manager'), NULL, 1, 0, 1, 133),
  ('AI-студия материалов', 'Студия хранит проверяемые черновики постов, поздравлений, кампаний и сценариев. Факты о продуктах должны браться только из утверждённой базы SWPro.', JSON_ARRAY(
    'Перед публикацией владелец проверяет и утверждает текст.',
    'Сезонные предложения не запускают рассылку автоматически.',
    'Голос и видео создаются только после подключения провайдера и необходимых согласий.'
  ), 'пост контент соцсети кампания сезон поздравление голос видео', JSON_ARRAY('superadmin','reseller','manager'), 'ai_studio.php', 1, 0, 1, 134);
