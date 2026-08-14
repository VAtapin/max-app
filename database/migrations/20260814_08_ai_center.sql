SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE subscription_plans
  ADD COLUMN ai_text_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_grace_days,
  ADD COLUMN ai_video_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ai_text_enabled,
  ADD COLUMN ai_personal_video_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ai_video_enabled,
  ADD COLUMN ai_realtime_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ai_personal_video_enabled,
  ADD COLUMN ai_text_monthly_limit INT UNSIGNED NULL AFTER ai_realtime_enabled,
  ADD COLUMN ai_video_monthly_seconds INT UNSIGNED NULL AFTER ai_text_monthly_limit,
  ADD COLUMN ai_personal_video_monthly_seconds INT UNSIGNED NULL AFTER ai_video_monthly_seconds,
  ADD COLUMN ai_max_video_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER ai_personal_video_monthly_seconds;

ALTER TABLE consultant_profiles
  ADD COLUMN ai_tone VARCHAR(50) NOT NULL DEFAULT 'friendly' AFTER welcome_video_url,
  ADD COLUMN ai_address_form ENUM('formal','informal','adaptive') NOT NULL DEFAULT 'formal' AFTER ai_tone,
  ADD COLUMN ai_greeting_style TEXT NULL AFTER ai_address_form,
  ADD COLUMN ai_persona_notes TEXT NULL AFTER ai_greeting_style,
  ADD COLUMN ai_forbidden_phrases TEXT NULL AFTER ai_persona_notes,
  ADD COLUMN ai_handoff_rules TEXT NULL AFTER ai_forbidden_phrases;

ALTER TABLE help_faq_sections
  ADD COLUMN keywords VARCHAR(500) NULL AFTER items_json,
  ADD COLUMN allowed_roles JSON NULL AFTER keywords,
  ADD COLUMN page_context VARCHAR(190) NULL AFTER allowed_roles,
  ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER page_context;

CREATE TABLE IF NOT EXISTS ai_knowledge_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin','reseller','manager') NOT NULL DEFAULT 'superadmin',
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  audience ENUM('admin','client','both') NOT NULL DEFAULT 'both',
  source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
  source_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  keywords VARCHAR(500) NULL,
  allowed_roles JSON NULL,
  page_context VARCHAR(190) NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ai_knowledge_scope (owner_type, owner_id, audience, is_active, is_approved),
  INDEX idx_ai_knowledge_source (source_type, source_id),
  INDEX idx_ai_knowledge_page (page_context),
  CONSTRAINT fk_ai_knowledge_approved_by FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_knowledge_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('admin','client') NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  end_user_id BIGINT UNSIGNED NULL,
  owner_type ENUM('superadmin','reseller','manager') NOT NULL DEFAULT 'superadmin',
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  channel ENUM('admin','telegram','VK','OK','MAX','web') NOT NULL DEFAULT 'admin',
  external_thread_id VARCHAR(190) NULL,
  context_page VARCHAR(190) NULL,
  summary TEXT NULL,
  status ENUM('active','handoff','closed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ai_conversation_admin (admin_user_id, status, updated_at),
  INDEX idx_ai_conversation_client (end_user_id, status, updated_at),
  INDEX idx_ai_conversation_owner (owner_type, owner_id, status),
  CONSTRAINT fk_ai_conversation_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ai_conversation_client FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  content MEDIUMTEXT NOT NULL,
  output_mode ENUM('text','audio','video') NOT NULL DEFAULT 'text',
  media_url VARCHAR(500) NULL,
  citations_json JSON NULL,
  provider VARCHAR(50) NULL,
  model VARCHAR(100) NULL,
  input_tokens INT UNSIGNED NULL,
  output_tokens INT UNSIGNED NULL,
  cost_amount DECIMAL(12,6) NULL,
  safety_status ENUM('ok','refused','handoff','error') NOT NULL DEFAULT 'ok',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_message_conversation (conversation_id, id),
  CONSTRAINT fk_ai_message_conversation FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_avatars (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller','manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'heygen',
  provider_avatar_id VARCHAR(190) NULL,
  avatar_type ENUM('photo','digital_twin') NOT NULL DEFAULT 'photo',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  source_photo_path VARCHAR(500) NULL,
  source_video_path VARCHAR(500) NULL,
  preview_video_path VARCHAR(500) NULL,
  voice_id VARCHAR(190) NULL,
  voice_name VARCHAR(190) NULL,
  background_key VARCHAR(100) NULL,
  pose_key VARCHAR(100) NULL,
  consent_confirmed_at DATETIME NULL,
  consent_text_version VARCHAR(50) NULL,
  status ENUM('draft','processing','review','approved','rejected','failed') NOT NULL DEFAULT 'draft',
  provider_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_avatar_owner_version (owner_type, owner_id, version),
  INDEX idx_ai_avatar_owner_status (owner_type, owner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_video_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  avatar_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NULL,
  message_id BIGINT UNSIGNED NULL,
  end_user_id BIGINT UNSIGNED NULL,
  purpose ENUM('welcome','answer','checkup_result','material') NOT NULL DEFAULT 'answer',
  personalization_level ENUM('general','personal') NOT NULL DEFAULT 'personal',
  script_text MEDIUMTEXT NOT NULL,
  script_hash CHAR(64) NOT NULL,
  provider_job_id VARCHAR(190) NULL,
  provider VARCHAR(50) NOT NULL DEFAULT 'heygen',
  output_path VARCHAR(500) NULL,
  duration_seconds DECIMAL(8,2) NULL,
  status ENUM('queued','processing','ready','failed','cancelled') NOT NULL DEFAULT 'queued',
  error_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX idx_ai_video_avatar (avatar_id, status, created_at),
  INDEX idx_ai_video_client (end_user_id, created_at),
  INDEX idx_ai_video_hash (avatar_id, personalization_level, script_hash, status),
  CONSTRAINT fk_ai_video_avatar FOREIGN KEY (avatar_id) REFERENCES ai_avatars(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ai_video_conversation FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_video_message FOREIGN KEY (message_id) REFERENCES ai_messages(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_video_client FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin','reseller','manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  admin_user_id BIGINT UNSIGNED NULL,
  end_user_id BIGINT UNSIGNED NULL,
  event_type ENUM('text','video','personal_video','realtime') NOT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
  provider VARCHAR(50) NULL,
  model VARCHAR(100) NULL,
  cost_amount DECIMAL(12,6) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_usage_owner_month (owner_type, owner_id, event_type, created_at),
  INDEX idx_ai_usage_admin (admin_user_id, created_at),
  INDEX idx_ai_usage_client (end_user_id, created_at),
  CONSTRAINT fk_ai_usage_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ai_usage_client FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('ai.enabled', '0', 'Главный выключатель AI-центра SWPro'),
  ('ai.external_processing_enabled', '0', 'Явное разрешение внешней обработки только утверждённых обезличенных материалов'),
  ('ai.text_provider', 'swpro', 'Провайдер текстовых ответов'),
  ('ai.text_model', 'gpt-5-mini', 'Основная модель для массовых ответов'),
  ('ai.complex_model', 'gpt-5', 'Модель для сложных ответов'),
  ('ai.video_provider', 'disabled', 'Провайдер видеоаватаров'),
  ('ai.minimum_source_score', '2', 'Минимальный балл совпадения с собственной базой знаний'),
  ('ai.admin_system_prompt', 'Отвечай только по предоставленным материалам SWPro. Если ответа нет, прямо сообщи об этом.', 'Правила помощника админки'),
  ('ai.client_system_prompt', 'Веди спокойный естественный диалог, используй только уместные разрешённые данные и предоставленные материалы. Не ставь диагнозы и не придумывай рекомендации.', 'Правила клиентского помощника')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO help_faq_sections
  (title, body, items_json, keywords, allowed_roles, page_context, ai_enabled, is_featured, is_active, sort_order)
VALUES
  ('ИИ-помощник в админке', 'Кнопка помощника находится справа внизу. Он отвечает только по утверждённым материалам HELP и данным SWPro, доступным вашей роли.', JSON_ARRAY(
    'Опишите задачу обычными словами и укажите, что именно хотите настроить.',
    'Ответ содержит источники из HELP или других утверждённых материалов.',
    'Если надёжного ответа нет, помощник предложит обратиться в поддержку и не будет придумывать инструкцию.',
    'Не отправляйте в чат пароли, токены сообществ и другие секреты.'
  ), 'ИИ помощник чат вопрос инструкция поддержка', JSON_ARRAY('superadmin','reseller','manager'), NULL, 1, 0, 1, 125),
  ('Как готовить материалы для ИИ', 'ИИ использует только активные и утверждённые материалы. Чем точнее заполнены продукты, инструкции, ограничения и правила рекомендаций, тем полезнее и безопаснее ответ.', JSON_ARRAY(
    'Заполняйте состав, применение, предупреждения и противопоказания у каждого продукта.',
    'Для результатов чек-апа добавляйте утверждённые пояснения и советы по каждому диапазону шкалы.',
    'Лидер и консультант заполняют мини-страницу и стиль общения, чтобы персональный помощник говорил естественно.',
    'Не добавляйте медицинские обещания и сведения, которые нельзя подтвердить.'
  ), 'база знаний материалы продукты чек-ап рекомендации аватар', JSON_ARRAY('superadmin','reseller','manager'), NULL, 1, 0, 1, 126)
ON DUPLICATE KEY UPDATE body = VALUES(body), items_json = VALUES(items_json), ai_enabled = 1, is_active = 1;

INSERT INTO help_faq_sections
  (title, body, items_json, keywords, allowed_roles, page_context, ai_enabled, is_featured, is_active, sort_order)
VALUES
  ('Моя подписка и оплата', 'В разделе «Моя подписка» показаны текущий тариф, период, состояние оплаты и доступные лимиты. Супер-администратор ведёт общий учёт в разделе «Бухгалтерия».', JSON_ARRAY(
    'Тариф лидера определяет лимиты команды и доступные AI-функции.',
    'При оплате по факту счёт формируется за завершённый календарный месяц.',
    'Просрочка ограничивает клиентские приложения, но не удаляет данные команды.'
  ), 'подписка тариф оплата счет лимит бухгалтерия', JSON_ARRAY('superadmin','reseller','manager'), 'billing.php', 1, 0, 1, 127),
  ('Безопасность входа в админку', 'Для административных пользователей можно включить двухфакторную защиту. После ввода пароля система попросит одноразовый код из приложения-аутентификатора.', JSON_ARRAY(
    'Не передавайте пароль и одноразовые коды другим людям.',
    'API-ключи и токены нельзя отправлять в чат помощника.',
    'При потере доступа обратитесь к супер-администратору.'
  ), 'безопасность вход 2fa двухфакторная защита код', JSON_ARRAY('superadmin','reseller','manager'), 'account.php', 1, 0, 1, 128),
  ('Объединение профилей клиента', 'Один человек может использовать Web, Telegram, VK, OK или MAX. Похожие профили не объединяются автоматически: пользователь подтверждает связь аккаунтов либо администратор выполняет проверенное объединение.', JSON_ARRAY(
    'Перед объединением сверяйте имя, город, возраст и принадлежность аккаунтов.',
    'Основным остаётся выбранный профиль, а история и платформы переносятся в него.',
    'Нельзя объединять аккаунты только из-за похожего имени.'
  ), 'объединить клиент аккаунт профиль telegram vk max web', JSON_ARRAY('superadmin','reseller','manager'), 'crud.php?module=users', 1, 0, 1, 129),
  ('Шаблоны мини-сайта', 'Шаблон задаёт стартовое оформление и содержание мини-сайта. Лидер может подготовить командный вариант, а консультант — персонализировать свою копию.', JSON_ARRAY(
    'Применение шаблона заполняет профиль и блоки стартовыми значениями.',
    'После личного изменения профиль считается настроенным владельцем.',
    'Сброс к родительскому варианту заменяет личные настройки актуальным шаблоном.'
  ), 'шаблон мини сайт профиль блоки применить сбросить', JSON_ARRAY('superadmin','reseller','manager'), 'crud.php?module=site_templates', 1, 0, 1, 130);
